<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\EnrollInSequenceRequest;
use App\Http\Requests\Crm\StoreCrmSequenceRequest;
use App\Http\Requests\Crm\UpdateCrmSequenceRequest;
use App\Models\CrmSequence;
use App\Models\CrmSequenceEnrollment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmSequenceController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function sequences(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.sequence.view'), 403);

        $sequences = CrmSequence::where('tenant_id', $this->tenantId($request))
            ->with('steps')
            ->withCount('enrollments')
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::paginated($sequences);
    }

    public function showSequence(CrmSequence $sequence): JsonResponse
    {
        $sequence->load(['steps' => fn ($q) => $q->orderBy('step_order'), 'enrollments.customer:id,name']);

        return ApiResponse::data($sequence);
    }

    public function storeSequence(StoreCrmSequenceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $sequence = DB::transaction(function () use ($data, $request) {
            $sequence = CrmSequence::create([
                'tenant_id' => $this->tenantId($request),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'total_steps' => count($data['steps']),
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['steps'] as $step) {
                $sequence->steps()->create($step);
            }

            return $sequence;
        });

        return ApiResponse::data($sequence->load('steps'), 201);
    }

    public function updateSequence(UpdateCrmSequenceRequest $request, CrmSequence $sequence): JsonResponse
    {
        $data = $request->validated();
        $sequence->update($data);

        return ApiResponse::data($sequence);
    }

    public function destroySequence(CrmSequence $sequence): JsonResponse
    {
        try {
            $sequence->enrollments()->where('status', 'active')->update(['status' => 'cancelled']);
            $sequence->delete();

            return ApiResponse::message('Cadência removida');
        } catch (\Exception $e) {
            Log::error('CrmSequence destroySequence failed', ['sequence_id' => $sequence->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover cadência', 500);
        }
    }

    public function enrollInSequence(EnrollInSequenceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $existing = CrmSequenceEnrollment::where('sequence_id', $data['sequence_id'])
            ->where('customer_id', $data['customer_id'])
            ->active()
            ->first();

        if ($existing) {
            return ApiResponse::message('Cliente já inscrito nesta cadência', 422);
        }

        $sequence = CrmSequence::findOrFail($data['sequence_id']);
        $firstStep = $sequence->steps()->orderBy('step_order')->first();

        $enrollment = CrmSequenceEnrollment::create([
            'tenant_id' => $this->tenantId($request),
            'sequence_id' => $data['sequence_id'],
            'customer_id' => $data['customer_id'],
            'deal_id' => $data['deal_id'] ?? null,
            'current_step' => 0,
            'next_action_at' => now()->addDays($firstStep?->delay_days ?? 0),
            'enrolled_by' => $request->user()->id,
        ]);

        return ApiResponse::data($enrollment, 201);
    }

    public function unenrollFromSequence(CrmSequenceEnrollment $enrollment): JsonResponse
    {
        $enrollment->update(['status' => 'cancelled']);

        return ApiResponse::message('Inscrição cancelada');
    }

    public function sequenceEnrollments(CrmSequence $sequence): JsonResponse
    {
        $enrollments = $sequence->enrollments()->with('customer:id,name')->orderByDesc('created_at')->paginate(15);

        return ApiResponse::paginated($enrollments);
    }
}
