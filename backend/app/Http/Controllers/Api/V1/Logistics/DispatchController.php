<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\StoreDispatchRuleRequest;
use App\Http\Requests\Logistics\UpdateDispatchRuleRequest;
use App\Models\AutoAssignmentRule;
use App\Models\WorkOrder;
use App\Services\AutoAssignmentService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly AutoAssignmentService $autoAssignmentService
    ) {}

    public function autoAssignRules(Request $request): JsonResponse
    {
        $paginator = AutoAssignmentRule::query()
            ->where('tenant_id', $this->tenantId())
            ->orderBy('priority')
            ->paginate(min((int) $request->input('per_page', 20), 100));

        return ApiResponse::paginated($paginator);
    }

    public function storeAutoAssignRule(StoreDispatchRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $rule = AutoAssignmentRule::create([
            'tenant_id' => $this->tenantId(),
            'name' => $validated['name'],
            'entity_type' => 'work_order',
            'strategy' => $this->resolveStrategy($validated['action'] ?? []),
            'conditions' => $validated['criteria'] ?? [],
            'technician_ids' => [],
            'required_skills' => [],
            'priority' => $validated['priority'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return ApiResponse::data($rule, 201);
    }

    public function updateAutoAssignRule(UpdateDispatchRuleRequest $request, AutoAssignmentRule $rule): JsonResponse
    {
        if ($response = $this->ensureTenantOwnership($rule, 'Regra')) {
            return $response;
        }

        $validated = $request->validated();

        $payload = [];

        if (array_key_exists('name', $validated)) {
            $payload['name'] = $validated['name'];
        }

        if (array_key_exists('priority', $validated)) {
            $payload['priority'] = $validated['priority'];
        }

        if (array_key_exists('is_active', $validated)) {
            $payload['is_active'] = (bool) $validated['is_active'];
        }

        if (array_key_exists('criteria', $validated)) {
            $payload['conditions'] = $validated['criteria'] ?? [];
        }

        if (array_key_exists('action', $validated)) {
            $payload['strategy'] = $this->resolveStrategy($validated['action'] ?? []);
        }

        $rule->update($payload);

        return ApiResponse::data($rule->fresh());
    }

    public function deleteAutoAssignRule(AutoAssignmentRule $rule): JsonResponse
    {
        if ($response = $this->ensureTenantOwnership($rule, 'Regra')) {
            return $response;
        }

        $rule->forceDelete();

        return ApiResponse::message('Regra excluída.');
    }

    public function triggerAutoAssign(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if ($response = $this->ensureTenantOwnership($workOrder, 'Ordem de serviço')) {
            return $response;
        }

        $technician = $this->autoAssignmentService->assignWorkOrder($workOrder);

        if (! $technician) {
            return ApiResponse::message('Nenhum técnico elegível encontrado para auto-assign.', 404);
        }

        return ApiResponse::data([
            'message' => "Auto-assigned to {$technician->name}",
            'technician' => $technician->only('id', 'name', 'email'),
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $action
     */
    private function resolveStrategy(array $action): string
    {
        return match ($action['assignTo'] ?? null) {
            'closest' => 'proximity',
            'least_loaded' => 'least_loaded',
            'skill_match' => 'skill_match',
            default => 'round_robin',
        };
    }
}
