<?php

namespace App\Actions\Report;

use App\Models\CommissionEvent;
use Illuminate\Support\Facades\DB;

class GenerateCommissionsReportAction extends BaseReportAction
{
    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public function execute(int $tenantId, array $filters): array
    {

        $from = $this->validatedDate($filters, 'from', now()->startOfMonth()->toDateString());
        $to = $this->validatedDate($filters, 'to', now()->toDateString());
        $osNumber = $this->osNumberFilter($filters);
        $branchId = $this->branchId($filters);

        $byTechQuery = CommissionEvent::join('users', 'users.id', '=', 'commission_events.user_id')
            ->where('commission_events.tenant_id', $tenantId)
            ->whereBetween('commission_events.created_at', [$from, "{$to} 23:59:59"]);

        if ($osNumber || $branchId) {
            $byTechQuery->join('work_orders as wo_comm', 'wo_comm.id', '=', 'commission_events.work_order_id');
            if ($osNumber) {
                $byTechQuery->where(function ($q) use ($osNumber) {
                    $q->where('wo_comm.os_number', 'like', "%{$osNumber}%")
                        ->orWhere('wo_comm.number', 'like', "%{$osNumber}%");
                });
            }
            if ($branchId) {
                $byTechQuery->where('wo_comm.branch_id', $branchId);
            }
        }

        $byTech = $byTechQuery
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(*) as events_count'),
            )
            ->selectRaw(
                'SUM(CASE WHEN commission_events.status IN (?, ?, ?) THEN commission_amount ELSE 0 END) as total_commission',
                [CommissionEvent::STATUS_PENDING, CommissionEvent::STATUS_APPROVED, CommissionEvent::STATUS_PAID]
            )
            ->selectRaw(
                'SUM(CASE WHEN commission_events.status = ? THEN commission_amount ELSE 0 END) as pending',
                [CommissionEvent::STATUS_PENDING]
            )
            ->selectRaw(
                'SUM(CASE WHEN commission_events.status = ? THEN commission_amount ELSE 0 END) as paid',
                [CommissionEvent::STATUS_PAID]
            )
            ->groupBy('users.id', 'users.name')
            ->get();

        $byStatusQuery = CommissionEvent::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(commission_amount) as total'));
        $this->applyWorkOrderFilter($byStatusQuery, 'workOrder', $osNumber);
        if ($branchId) {
            $byStatusQuery->where(function ($q) use ($branchId) {
                $q->whereHas('workOrder', fn ($wo) => $wo->where('branch_id', $branchId))
                    ->orWhereNull('work_order_id');
            });
        }

        $byStatus = $byStatusQuery->groupBy('status')->get();

        return [
            'period' => ['from' => $from, 'to' => $to, 'os_number' => $osNumber],
            'by_technician' => $byTech,
            'by_status' => $byStatus,
        ];
    }
}
