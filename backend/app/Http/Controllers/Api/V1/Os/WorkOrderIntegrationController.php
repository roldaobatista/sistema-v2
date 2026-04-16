<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FiscalNote;
use App\Models\SatisfactionSurvey;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Models\WorkOrderStatusHistory;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WorkOrderIntegrationController extends Controller
{
    use ResolvesCurrentTenant;

    public function satisfaction(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        $survey = SatisfactionSurvey::where('work_order_id', $workOrder->id)->first();

        if (! $survey) {
            return ApiResponse::data(null);
        }

        return ApiResponse::data($survey);
    }

    public function costEstimate(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }
        $workOrder->load('items');
        $totals = $workOrder->calculateFinancialTotals();
        $profitability = $workOrder->estimated_profit;

        return ApiResponse::data([
            'items' => $totals['items'],
            'items_subtotal' => $totals['items_subtotal'],
            'items_discount' => $totals['items_discount'],
            'displacement_value' => $totals['displacement_value'],
            'global_discount' => $totals['global_discount'],
            'grand_total' => $totals['grand_total'],
            'revenue' => $profitability['revenue'],
            'total_cost' => $profitability['costs'],
            'profit' => $profitability['profit'],
            'margin_pct' => $profitability['margin_pct'],
            'cost_breakdown' => $profitability['breakdown'],
        ]);
    }

    public function fiscalNotes(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        $notes = FiscalNote::where('tenant_id', $workOrder->tenant_id)
            ->where('work_order_id', $workOrder->id)
            ->orderByDesc('created_at')
            ->simplePaginate(15);

        return ApiResponse::paginated($notes);
    }

    public function auditTrail(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        try {
            $logs = collect();

            if (Schema::hasTable('audit_logs')) {
                $itemIds = $workOrder->items()->pluck('id');

                $logs = AuditLog::with('user:id,name')
                    ->where(function ($query) use ($workOrder, $itemIds) {
                        $query->where(function ($subQuery) use ($workOrder) {
                            $subQuery->where('auditable_type', WorkOrder::class)
                                ->where('auditable_id', $workOrder->id);
                        });

                        if ($itemIds->isNotEmpty()) {
                            $query->orWhere(function ($subQuery) use ($itemIds) {
                                $subQuery->where('auditable_type', WorkOrderItem::class)
                                    ->whereIn('auditable_id', $itemIds);
                            });
                        }
                    })
                    ->orderByDesc('created_at')
                    ->limit(200)
                    ->get()
                    ->map(function ($log) {
                        $actionValue = $log->action instanceof AuditAction
                            ? $log->action->value
                            : (string) $log->action;

                        $actionLabel = $log->action instanceof AuditAction
                            ? $log->action->label()
                            : (AuditAction::tryFrom($actionValue)?->label() ?? $actionValue);

                        return [
                            'id' => $log->id,
                            'action' => $actionValue,
                            'action_label' => $actionLabel,
                            'description' => $log->description,
                            'entity_type' => $log->auditable_type ? class_basename($log->auditable_type) : null,
                            'entity_id' => $log->auditable_id,
                            'user' => $log->user,
                            'old_values' => $log->old_values,
                            'new_values' => $log->new_values,
                            'ip_address' => $log->ip_address,
                            'created_at' => $log->created_at,
                        ];
                    });
            }

            $statusHistory = $this->buildStatusHistoryAuditEntries($workOrder);

            $combined = $logs->concat($statusHistory)
                ->sortByDesc('created_at')
                ->values();

            return ApiResponse::data($combined);
        } catch (\Throwable $e) {
            Log::error('WorkOrder auditTrail failed', [
                'work_order_id' => $workOrder->id,
                'tenant_id' => $workOrder->tenant_id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::data(
                $this->buildStatusHistoryAuditEntries($workOrder)->values(),
                200,
                ['warning' => 'Não foi possivel carregar a trilha completa de auditoria.']
            );
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildStatusHistoryAuditEntries(WorkOrder $workOrder): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, WorkOrderStatusHistory> $history */
        $history = $workOrder->statusHistory()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();

        /** @var Collection<int, array<string, mixed>> $entries */
        $entries = $history->map(fn (WorkOrderStatusHistory $entry): array => [
            'id' => 'sh-'.$entry->id,
            'action' => 'status_changed',
            'action_label' => 'Mudança de Status',
            'description' => sprintf(
                'Status alterado de "%s" para "%s"%s',
                $entry->from_status instanceof \BackedEnum ? $entry->from_status->value : ($entry->from_status ?? '—'),
                $entry->to_status instanceof \BackedEnum ? $entry->to_status->value : ($entry->to_status ?? '—'),
                $entry->notes ? " — {$entry->notes}" : ''
            ),
            'entity_type' => 'WorkOrder',
            'entity_id' => $workOrder->id,
            'user' => $entry->user,
            'old_values' => ['status' => $entry->from_status instanceof \BackedEnum ? $entry->from_status->value : $entry->from_status],
            'new_values' => ['status' => $entry->to_status instanceof \BackedEnum ? $entry->to_status->value : $entry->to_status],
            'ip_address' => null,
            'created_at' => $entry->created_at,
        ]);

        return $entries;
    }

    public function statusHistoryAlias(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        return ApiResponse::data(
            $workOrder->statusHistory()
                ->where('tenant_id', $this->tenantId())
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->simplePaginate(30)
        );
    }
}
