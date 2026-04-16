<?php

namespace App\Actions\Report;

use App\Models\CrmDeal;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class GenerateCrmReportAction extends BaseReportAction
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
                $query->whereHas('assignee', fn ($q) => $q->where('branch_id', $branchId));
            }
        };

        $dealsByStatusQuery = CrmDeal::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"]);
        $applyBranchFilter($dealsByStatusQuery);
        $dealsByStatus = $dealsByStatusQuery
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(value) as value'))
            ->groupBy('status')
            ->get();

        $dealsBySellerQuery = CrmDeal::where('crm_deals.tenant_id', $tenantId)
            ->leftJoin('users', 'users.id', '=', 'crm_deals.assigned_to')
            ->whereBetween('crm_deals.created_at', [$from, "{$to} 23:59:59"]);

        if ($branchId) {
            $dealsBySellerQuery->where('users.branch_id', $branchId);
        }

        $dealsBySeller = $dealsBySellerQuery
            ->select(
                'users.id as owner_id',
                'users.name as owner_name',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(crm_deals.value) as value')
            )
            ->groupBy('users.id', 'users.name')
            ->get();

        $totalDealsQuery = CrmDeal::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"]);
        $applyBranchFilter($totalDealsQuery);
        $totalDeals = $totalDealsQuery->count();

        $wonDealsQuery = CrmDeal::where('tenant_id', $tenantId)
            ->where('status', CrmDeal::STATUS_WON)
            ->whereBetween('won_at', [$from, "{$to} 23:59:59"]);
        $applyBranchFilter($wonDealsQuery);
        $wonDeals = $wonDealsQuery->count();

        $revenueQuery = CrmDeal::where('tenant_id', $tenantId)
            ->where('status', CrmDeal::STATUS_WON)
            ->whereBetween('won_at', [$from, "{$to} 23:59:59"]);
        $applyBranchFilter($revenueQuery);
        $revenue = (string) $revenueQuery->sum('value');

        $totalValueQuery = CrmDeal::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"]);
        $applyBranchFilter($totalValueQuery);
        $totalValue = (string) $totalValueQuery->sum('value');

        $avgDealValue = $totalDeals > 0 ? round($totalValue / $totalDeals, 2) : 0;
        $conversionRate = $totalDeals > 0 ? round(($wonDeals / $totalDeals) * 100, 1) : 0;

        $healthSummaryQuery = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('health_score');

        if ($branchId) {
            $healthSummaryQuery->whereHas('assignedSeller', fn ($q) => $q->where('branch_id', $branchId));
        }

        $healthSummary = $healthSummaryQuery
            ->select(DB::raw('
                    SUM(CASE WHEN health_score >= 80 THEN 1 ELSE 0 END) as healthy,
                    SUM(CASE WHEN health_score >= 50 AND health_score < 80 THEN 1 ELSE 0 END) as at_risk,
                    SUM(CASE WHEN health_score < 50 THEN 1 ELSE 0 END) as critical
                '))
            ->first();

        $healthDistribution = [
            ['range' => 'Saudavel', 'count' => (int) ($healthSummary->healthy ?? 0)],
            ['range' => 'Risco', 'count' => (int) ($healthSummary->at_risk ?? 0)],
            ['range' => 'Critico', 'count' => (int) ($healthSummary->critical ?? 0)],
        ];

        return [
            'period' => ['from' => $from, 'to' => $to],
            'deals_by_status' => $dealsByStatus,
            'deals_by_seller' => $dealsBySeller,
            'total_deals' => $totalDeals,
            'won_deals' => $wonDeals,
            'conversion_rate' => $conversionRate,
            'revenue' => $revenue,
            'avg_deal_value' => $avgDealValue,
            'health_distribution' => $healthDistribution,
            'health_distribution_summary' => [
                'healthy' => (int) ($healthSummary->healthy ?? 0),
                'at_risk' => (int) ($healthSummary->at_risk ?? 0),
                'critical' => (int) ($healthSummary->critical ?? 0),
            ],
        ];
    }
}
