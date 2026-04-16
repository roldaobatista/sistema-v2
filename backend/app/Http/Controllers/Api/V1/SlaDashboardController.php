<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkOrderResource;
use App\Models\SlaPolicy;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SlaDashboardController extends Controller
{
    use ResolvesCurrentTenant;

    public function overview(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $total = WorkOrder::where('tenant_id', $tenantId)
                ->whereNotNull('sla_policy_id')
                ->count();

            $responseCumprido = WorkOrder::where('tenant_id', $tenantId)
                ->whereNotNull('sla_policy_id')
                ->whereNotNull('sla_responded_at')
                ->where('sla_response_breached', false)
                ->count();

            $responseEstourado = WorkOrder::where('tenant_id', $tenantId)
                ->where('sla_response_breached', true)
                ->count();

            $resolutionCumprido = WorkOrder::where('tenant_id', $tenantId)
                ->whereNotNull('sla_policy_id')
                ->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_INVOICED])
                ->where('sla_resolution_breached', false)
                ->count();

            $resolutionEstourado = WorkOrder::where('tenant_id', $tenantId)
                ->where('sla_resolution_breached', true)
                ->count();

            $emRisco = WorkOrder::where('tenant_id', $tenantId)
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '>', now())
                ->where('sla_due_at', '<', now()->addHours(4))
                ->whereNotIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_INVOICED, WorkOrder::STATUS_CANCELLED])
                ->count();

            return ApiResponse::data([
                'total_com_sla' => $total,
                'response' => [
                    'cumprido' => $responseCumprido,
                    'estourado' => $responseEstourado,
                    'taxa' => $total > 0 ? round(($responseCumprido / max($responseCumprido + $responseEstourado, 1)) * 100, 1) : 0,
                ],
                'resolution' => [
                    'cumprido' => $resolutionCumprido,
                    'estourado' => $resolutionEstourado,
                    'taxa' => $total > 0 ? round(($resolutionCumprido / max($resolutionCumprido + $resolutionEstourado, 1)) * 100, 1) : 0,
                ],
                'em_risco' => $emRisco,
            ]);
        } catch (\Exception $e) {
            Log::error('SlaDashboard overview failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar overview SLA.', 500);
        }
    }

    public function breachedOrders(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $orders = WorkOrder::where('tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->where('sla_response_breached', true)
                        ->orWhere('sla_resolution_breached', true);
                })
                ->with(['customer:id,name', 'assignee:id,name', 'slaPolicy:id,name'])
                ->orderByDesc('created_at')
                ->paginate(min($request->integer('per_page', 20), 100));

            return ApiResponse::paginated($orders, resourceClass: WorkOrderResource::class);
        } catch (\Exception $e) {
            Log::error('SlaDashboard breachedOrders failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar OS com SLA estourado.', 500);
        }
    }

    public function byTechnician(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $technicians = WorkOrder::where('work_orders.tenant_id', $tenantId)
                ->whereNotNull('sla_policy_id')
                ->whereNotNull('assigned_to')
                ->join('users', 'work_orders.assigned_to', '=', 'users.id')
                ->selectRaw('users.id, users.name, COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN sla_response_breached = 1 OR sla_resolution_breached = 1 THEN 1 ELSE 0 END) as breached')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total')
                ->limit(20)
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'total' => (int) $t->total,
                    'breached' => (int) $t->breached,
                    'compliance_rate' => $t->total > 0 ? round((($t->total - $t->breached) / $t->total) * 100, 1) : 100,
                ]);

            return ApiResponse::data($technicians);
        } catch (\Exception $e) {
            Log::error('SlaDashboard byTechnician failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar SLA por técnico.', 500);
        }
    }

    public function trends(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $weeks = $request->integer('weeks', 8);

            $data = collect();
            for ($i = $weeks - 1; $i >= 0; $i--) {
                $start = now()->subWeeks($i)->startOfWeek();
                $end = now()->subWeeks($i)->endOfWeek();

                $total = WorkOrder::where('tenant_id', $tenantId)
                    ->whereNotNull('sla_policy_id')
                    ->whereBetween('created_at', [$start, $end])
                    ->count();

                $breached = WorkOrder::where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$start, $end])
                    ->where(fn ($q) => $q->where('sla_response_breached', true)->orWhere('sla_resolution_breached', true))
                    ->count();

                $data->push([
                    'week' => $start->format('d/m'),
                    'total' => $total,
                    'breached' => $breached,
                    'compliance_rate' => $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100,
                ]);
            }

            return ApiResponse::data($data);
        } catch (\Exception $e) {
            Log::error('SlaDashboard trends failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar tendências SLA.', 500);
        }
    }

    public function byPolicy(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $policies = SlaPolicy::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get()
                ->map(function ($policy) use ($tenantId) {
                    $total = WorkOrder::where('tenant_id', $tenantId)
                        ->where('sla_policy_id', $policy->id)
                        ->count();
                    $breached = WorkOrder::where('tenant_id', $tenantId)
                        ->where('sla_policy_id', $policy->id)
                        ->where(fn ($q) => $q->where('sla_response_breached', true)->orWhere('sla_resolution_breached', true))
                        ->count();

                    return [
                        'id' => $policy->id,
                        'name' => $policy->name,
                        'priority' => $policy->priority,
                        'total' => $total,
                        'breached' => $breached,
                        'compliance_rate' => $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100,
                    ];
                });

            return ApiResponse::data($policies);
        } catch (\Exception $e) {
            Log::error('SlaDashboard byPolicy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar SLA por política.', 500);
        }
    }
}
