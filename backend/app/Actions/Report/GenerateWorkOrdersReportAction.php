<?php

namespace App\Actions\Report;

use Illuminate\Support\Facades\DB;

class GenerateWorkOrdersReportAction extends BaseReportAction
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
        $periodExpr = $this->yearMonthExpression('created_at');
        $avgExpr = $this->avgHoursExpression('created_at', 'completed_at');

        $baseQuery = fn () => DB::table('work_orders')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $byStatus = $baseQuery()
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy('status')
            ->get();

        $byPriority = $baseQuery()
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->get();

        $avgTime = DB::table('work_orders')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, "{$to} 23:59:59"])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw("{$avgExpr} as avg_hours")
            ->value('avg_hours');

        $monthly = $baseQuery()
            ->selectRaw("{$periodExpr} as period, COUNT(*) as count, SUM(total) as total")
            ->groupByRaw($periodExpr)
            ->orderBy('period')
            ->get();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'avg_completion_hours' => round((float) $avgTime, 1),
            'monthly' => $monthly,
        ];
    }
}
