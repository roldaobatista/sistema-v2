<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceOps\BulkCreateWorkOrdersRequest;
use App\Models\WorkOrder;
use App\Services\SlaEscalationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceOpsController extends Controller
{
    public function __construct(
        private readonly SlaEscalationService $slaService,
    ) {}

    public function slaDashboard(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);
        if ($tenantId === null) {
            return ApiResponse::message('Empresa atual não selecionada.', 403);
        }

        return ApiResponse::data($this->slaService->getDashboard($tenantId));
    }

    public function runSlaChecks(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);
        if ($tenantId === null) {
            return ApiResponse::message('Empresa atual não selecionada.', 403);
        }

        $results = $this->slaService->runSlaChecks($tenantId);

        return ApiResponse::data($results, 200, ['message' => 'Verificações de SLA concluídas.']);
    }

    public function slaStatus(WorkOrder $workOrder): JsonResponse
    {
        $evaluation = $this->slaService->evaluateSla($workOrder);

        return ApiResponse::data($evaluation ?? ['status' => 'ok', 'message' => 'No SLA configured or not at risk']);
    }

    public function bulkCreateWorkOrders(BulkCreateWorkOrdersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $template = $validated['template'] ?? [];
        $equipmentIds = $validated['equipment_ids'];
        $user = $request->user();
        $tenantId = $this->currentTenantId($request);
        if ($tenantId === null) {
            return ApiResponse::message('Empresa atual não selecionada.', 403);
        }

        $created = [];

        unset($template['tenant_id'], $template['created_by'], $template['status'], $template['equipment_id']);

        DB::beginTransaction();

        try {
            foreach ($equipmentIds as $equipmentId) {
                $workOrder = WorkOrder::create([
                    ...$template,
                    'equipment_id' => $equipmentId,
                    'tenant_id' => $tenantId,
                    'created_by' => $user->id,
                    'status' => 'open',
                ]);

                $created[] = $workOrder->id;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creating batch work orders', ['exception' => $e]);

            return ApiResponse::message('Erro ao criar lote.', 500);
        }

        return ApiResponse::data(
            ['work_order_ids' => $created],
            201,
            ['message' => count($created).' OS criada(s) com sucesso.']
        );
    }

    private function currentTenantId(Request $request): ?int
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? 0);

        return $tenantId > 0 ? $tenantId : null;
    }
}
