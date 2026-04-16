<?php

namespace App\Actions\Report;

use App\Models\AccountReceivable;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class GenerateCustomersReportAction extends BaseReportAction
{
    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public function execute(int $tenantId, array $filters): array
    {

        $from = $this->validatedDate($filters, 'from', now()->startOfMonth()->toDateString());
        $to = $this->validatedDate($filters, 'to', now()->toDateString());

        $branchId = $this->branchId($filters);

        $applyBranchFilter = function ($query) use ($branchId) {
            if ($branchId) {
                $query->whereHas('assignedSeller', fn ($q) => $q->where('branch_id', $branchId));
            }
        };

        $topByRevenueQuery = DB::table('accounts_receivable')
            ->join('work_orders', 'accounts_receivable.work_order_id', '=', 'work_orders.id')
            ->join('customers', 'work_orders.customer_id', '=', 'customers.id')
            ->where('accounts_receivable.tenant_id', $tenantId)
            ->whereBetween('accounts_receivable.due_date', [$from, "{$to} 23:59:59"])
            ->where('accounts_receivable.status', '!=', AccountReceivable::STATUS_CANCELLED);

        if ($branchId) {
            $topByRevenueQuery->where('work_orders.branch_id', $branchId);
        }

        $topByRevenue = $topByRevenueQuery
            ->select(
                'customers.id',
                'customers.name',
                DB::raw('COUNT(DISTINCT work_orders.id) as os_count'),
                DB::raw('SUM(accounts_receivable.amount_paid) as total_revenue')
            )
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_revenue')
            ->limit(20)
            ->get();

        $bySegmentQuery = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true);
        $applyBranchFilter($bySegmentQuery);
        $bySegment = $bySegmentQuery
            ->select('segment', DB::raw('COUNT(*) as count'))
            ->groupBy('segment')
            ->get();

        $totalActiveQuery = Customer::where('tenant_id', $tenantId)->where('is_active', true);
        $applyBranchFilter($totalActiveQuery);
        $totalActive = $totalActiveQuery->count();

        $newInPeriodQuery = Customer::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"]);
        $applyBranchFilter($newInPeriodQuery);
        $newInPeriod = $newInPeriodQuery->count();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'top_by_revenue' => $topByRevenue,
            'by_segment' => $bySegment,
            'total_active' => $totalActive,
            'new_in_period' => $newInPeriod,
        ];
    }
}
