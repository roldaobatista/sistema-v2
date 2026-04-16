<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advanced\CompleteFollowUpRequest;
use App\Http\Requests\Advanced\IndexFollowUpRequest;
use App\Http\Requests\Advanced\StoreFollowUpRequest;
use App\Http\Requests\Advanced\UpdateFollowUpRequest;
use App\Models\FollowUp;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FollowUpController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(IndexFollowUpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = FollowUp::where('tenant_id', $this->tenantId())
            ->with(['assignedTo:id,name', 'followable']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['assigned_to'])) {
            $query->where('assigned_to', $validated['assigned_to']);
        }
        if (! empty($validated['search'])) {
            $search = SearchSanitizer::escapeLike((string) $validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('notes', 'like', "%{$search}%")
                    ->orWhere('channel', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('result', 'like', "%{$search}%");
            });
        }
        if (! empty($validated['overdue'])) {
            $query->where('status', 'pending')->where('scheduled_at', '<', now());
        }

        $paginator = $query->orderBy('scheduled_at')
            ->paginate(min((int) ($validated['per_page'] ?? 20), 100))
            ->through(fn (FollowUp $followUp) => $this->formatFollowUp($followUp));

        return ApiResponse::paginated($paginator);
    }

    public function store(StoreFollowUpRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $request->merge([
            'channel' => $request->input('channel', $request->input('type')),
            'assigned_to' => $request->input('assigned_to', $request->user()?->id),
            'followable_type' => $request->input('followable_type', User::class),
            'followable_id' => $request->input('followable_id', $request->user()?->id),
        ]);

        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $tenantId;
            $followUp = FollowUp::create($validated);
            DB::commit();

            return ApiResponse::data($this->formatFollowUp($followUp->load('assignedTo:id,name', 'followable')), 201, ['message' => 'Follow-up agendado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FollowUp create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao agendar follow-up.', 500);
        }
    }

    public function update(UpdateFollowUpRequest $request, FollowUp $followUp): JsonResponse
    {
        if ((int) $followUp->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $request->merge([
            'channel' => $request->input('channel', $request->input('type')),
        ]);

        $validated = $request->validated();

        if (array_key_exists('status', $validated) && $validated['status'] === 'completed' && ! array_key_exists('completed_at', $validated)) {
            $validated['completed_at'] = now();
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== 'completed' && ! array_key_exists('completed_at', $validated)) {
            $validated['completed_at'] = null;
        }

        try {
            DB::beginTransaction();
            $followUp->update($validated);
            DB::commit();

            return ApiResponse::data($this->formatFollowUp($followUp->fresh()->load('assignedTo:id,name', 'followable')), 200, ['message' => 'Follow-up atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FollowUp update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar follow-up.', 500);
        }
    }

    public function complete(CompleteFollowUpRequest $request, FollowUp $followUp): JsonResponse
    {
        if ((int) $followUp->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $followUp->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => $validated['result'],
                'notes' => $validated['notes'] ?? $followUp->notes,
            ]);
            DB::commit();

            return ApiResponse::data($followUp->fresh(), 200, ['message' => 'Follow-up concluído']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FollowUp complete failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao concluir.', 500);
        }
    }

    public function destroy(FollowUp $followUp): JsonResponse
    {
        if ((int) $followUp->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        try {
            DB::beginTransaction();
            $followUp->delete();
            DB::commit();

            return ApiResponse::message('Follow-up removido.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FollowUp destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover follow-up.', 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFollowUp(FollowUp $followUp): array
    {
        $followable = $followUp->followable;
        $followableName = null;

        if ($followable) {
            foreach (['name', 'title', 'company_name', 'razao_social'] as $attribute) {
                $value = $followable->getAttribute($attribute);
                if (is_string($value) && trim($value) !== '') {
                    $followableName = $value;
                    break;
                }
            }
        }

        $assignedTo = $followUp->assignedTo;

        return [
            ...$followUp->toArray(),
            'type' => $followUp->channel,
            'customer' => $followableName ? ['name' => $followableName] : null,
            'responsible' => $assignedTo instanceof User
                ? [
                    'id' => $assignedTo->id,
                    'name' => $assignedTo->name,
                ]
                : null,
        ];
    }
}
