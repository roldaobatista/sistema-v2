<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmWebFormRequest;
use App\Http\Requests\Crm\SubmitCrmWebFormRequest;
use App\Http\Requests\Crm\UpdateCrmWebFormRequest;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmSequence;
use App\Models\CrmSequenceEnrollment;
use App\Models\CrmTrackingEvent;
use App\Models\CrmWebForm;
use App\Models\CrmWebFormSubmission;
use App\Models\Customer;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrmWebFormController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    private function ensureWebFormOwnership(CrmWebForm $form, Request $request): ?JsonResponse
    {
        if ((int) $form->tenant_id !== $this->tenantId($request)) {
            return ApiResponse::message('Formulario nao encontrado.', 404);
        }

        return null;
    }

    public function webForms(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.form.view'), 403);

        return ApiResponse::paginated(
            CrmWebForm::where('tenant_id', $this->tenantId($request))
                ->withCount('submissions')
                ->orderByDesc('created_at')
                ->paginate(15)
        );
    }

    public function webFormOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);

        return ApiResponse::data([
            'pipelines' => CrmPipeline::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'sequences' => CrmSequence::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'status']),
            'users' => User::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeWebForm(StoreCrmWebFormRequest $request): JsonResponse
    {
        $data = $request->validated();
        $form = CrmWebForm::create([
            ...$data,
            'tenant_id' => $this->tenantId($request),
            'slug' => Str::slug($data['name']).'-'.Str::random(6),
        ]);

        return ApiResponse::data($form, 201);
    }

    public function updateWebForm(UpdateCrmWebFormRequest $request, CrmWebForm $form): JsonResponse
    {
        if ($error = $this->ensureWebFormOwnership($form, $request)) {
            return $error;
        }
        $form->update($request->validated());

        return ApiResponse::data($form);
    }

    public function destroyWebForm(CrmWebForm $form): JsonResponse
    {
        if ($error = $this->ensureWebFormOwnership($form, request())) {
            return $error;
        }

        try {
            DB::transaction(function () use ($form) {
                $form->submissions()->delete();
                $form->delete();
            });

            return ApiResponse::message('Formulário removido');
        } catch (\Exception $e) {
            Log::error('CrmWebForm destroyWebForm failed', ['form_id' => $form->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover formulário', 500);
        }
    }

    public function submitWebForm(SubmitCrmWebFormRequest $request, string $slug): JsonResponse
    {
        $form = CrmWebForm::where('slug', $slug)->active()->firstOrFail();
        $validated = $request->validated();
        $submission = DB::transaction(function () use ($form, $request, $validated) {
            $data = $validated;
            $assignedSellerId = User::query()->where('tenant_id', $form->tenant_id)->whereKey($form->assign_to)->value('id');

            $customer = null;
            $email = $data['email'] ?? null;
            $phone = $data['phone'] ?? $data['telefone'] ?? null;

            if ($email || $phone) {
                $customer = Customer::where('tenant_id', $form->tenant_id)
                    ->when($email, fn ($q) => $q->where('email', $email))
                    ->when(! $email && $phone, fn ($q) => $q->where('phone', $phone))
                    ->first();

                if (! $customer) {
                    $customer = Customer::create([
                        'tenant_id' => $form->tenant_id,
                        'name' => $data['name'] ?? $data['nome'] ?? 'Lead Web Form',
                        'email' => $email, 'phone' => $phone,
                        'source' => 'web_form',
                        'assigned_seller_id' => $assignedSellerId,
                    ]);
                }
            }

            $deal = null;
            if ($form->pipeline_id && $customer) {
                $pipeline = CrmPipeline::query()->where('tenant_id', $form->tenant_id)->find($form->pipeline_id);
                $firstStage = $pipeline?->stages()->orderBy('sort_order')->first();
                if ($pipeline && $firstStage) {
                    $deal = CrmDeal::create([
                        'tenant_id' => $form->tenant_id, 'customer_id' => $customer->id,
                        'pipeline_id' => $pipeline->id, 'stage_id' => $firstStage->id,
                        'title' => 'Lead via formulário: '.($customer->name ?? 'Web'),
                        'source' => 'prospeccao', 'assigned_to' => $assignedSellerId,
                    ]);
                }
            }

            if ($form->sequence_id && $customer) {
                $sequence = CrmSequence::query()->where('tenant_id', $form->tenant_id)->find($form->sequence_id);
                $firstStep = $sequence?->steps()->orderBy('step_order')->first();
                if ($sequence) {
                    CrmSequenceEnrollment::create([
                        'tenant_id' => $form->tenant_id, 'sequence_id' => $sequence->id,
                        'customer_id' => $customer->id, 'deal_id' => $deal?->id,
                        'next_action_at' => now()->addDays($firstStep?->delay_days ?? 0),
                    ]);
                }
            }

            $submission = CrmWebFormSubmission::create([
                'form_id' => $form->id, 'customer_id' => $customer?->id, 'deal_id' => $deal?->id,
                'data' => $data, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                'utm_source' => $data['utm_source'] ?? null, 'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
            ]);

            $form->increment('submissions_count');

            CrmTrackingEvent::create([
                'tenant_id' => $form->tenant_id, 'trackable_type' => CrmWebFormSubmission::class,
                'trackable_id' => $submission->id, 'customer_id' => $customer?->id, 'deal_id' => $deal?->id,
                'event_type' => 'form_submitted', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                'metadata' => ['form_id' => $form->id, 'form_slug' => $form->slug],
            ]);

            return $submission;
        });

        return ApiResponse::data([
            'message' => $form->success_message ?? 'Formulário enviado com sucesso!',
            'redirect_url' => $form->redirect_url,
        ]);
    }
}
