<?php

namespace App\Actions\Report;

use App\Models\Quote;
use Illuminate\Support\Facades\DB;

class GenerateQuotesReportAction extends BaseReportAction
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

        $baseQuery = fn () => Quote::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
            ->when($branchId, fn ($q) => $q->whereHas('seller', fn ($u) => $u->where('branch_id', $branchId)));

        $byStatus = (clone $baseQuery())
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy('status')
            ->get();

        $bySeller = Quote::where('quotes.tenant_id', $tenantId)
            ->join('users', 'users.id', '=', 'quotes.seller_id')
            ->whereBetween('quotes.created_at', [$from, "{$to} 23:59:59"])
            ->when($branchId, fn ($q) => $q->where('users.branch_id', $branchId))
            ->select('users.id', 'users.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(quotes.total) as total'))
            ->groupBy('users.id', 'users.name')
            ->get();

        $totalQuotes = (clone $baseQuery())->count();

        $approved = (clone $baseQuery())
            ->whereIn('status', [Quote::STATUS_APPROVED, Quote::STATUS_INVOICED])
            ->count();

        $conversionRate = $totalQuotes > 0 ? round(($approved / $totalQuotes) * 100, 1) : 0;

        return [
            'period' => ['from' => $from, 'to' => $to],
            'by_status' => $byStatus,
            'by_seller' => $bySeller,
            'total' => $totalQuotes,
            'approved' => $approved,
            'conversion_rate' => $conversionRate,
        ];
    }
}
