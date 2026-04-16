<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WorkOrderDashboardController extends Controller
{
    use ResolvesCurrentTenant;

    public function dashboardStats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkOrder::class);
        $tenantId = $this->tenantId();
        $from = $request->query('from');
        $to = $request->query('to');
        $cacheKey = "wo_dashboard:{$tenantId}:{$from}:{$to}";

        $data = Cache::remember($cacheKey, 60, function () use ($tenantId, $from, $to) {
            $base = WorkOrder::where('work_orders.tenant_id', $tenantId)
                ->when($from, fn ($q) => $q->where('work_orders.created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('work_orders.created_at', '<=', $to.' 23:59:59'));

            // Status counts
            $statusCounts = (clone $base)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            // Avg completion time (in hours)
            $isSqlite = DB::getDriverName() === 'sqlite';
            $avgExpr = $isSqlite
                ? "AVG((strftime('%s', completed_at) - strftime('%s', started_at)) / 3600.0)"
                : 'AVG(TIMESTAMPDIFF(HOUR, started_at, completed_at))';
            $avgCompletionHours = (clone $base)
                ->whereNotNull('completed_at')
                ->whereNotNull('started_at')
                ->selectRaw("{$avgExpr} as avg_hours")
                ->value('avg_hours');

            // Revenue this month
            $monthRevenue = (clone $base)
                ->where('status', WorkOrder::STATUS_INVOICED)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total');

            // SLA compliance
            $totalWithSla = (clone $base)->whereNotNull('sla_due_at')->count();
            $slaBreached = (clone $base)
                ->whereNotNull('sla_due_at')
                ->where(function ($q) {
                    $q->whereColumn('completed_at', '>', 'sla_due_at')
                        ->orWhere(function ($q2) {
                            $q2->whereNull('completed_at')->where('sla_due_at', '<', now());
                        });
                })
                ->count();
            $slaCompliance = $totalWithSla > 0 ? round((($totalWithSla - $slaBreached) / $totalWithSla) * 100, 1) : 100;

            // Overdue orders (active OSes with passed SLA)
            $overdueOrders = (clone $base)
                ->whereNotIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED, WorkOrder::STATUS_CANCELLED])
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->count();

            // Service type counts
            $serviceTypeCounts = (clone $base)
                ->whereNotNull('service_type')
                ->select('service_type', DB::raw('count(*) as count'))
                ->groupBy('service_type')
                ->pluck('count', 'service_type');

            // Top 5 customers
            $topCustomers = (clone $base)
                ->join('customers', 'work_orders.customer_id', '=', 'customers.id')
                ->select('customers.name', DB::raw('count(*) as total_os'), DB::raw('sum(work_orders.total) as revenue'))
                ->groupBy('customers.id', 'customers.name')
                ->orderByDesc('total_os')
                ->limit(5)
                ->toBase()
                ->get();

            // Daily trend (count of OS created per day within range)
            $dateExpr = $isSqlite ? "strftime('%Y-%m-%d', work_orders.created_at)" : 'DATE(work_orders.created_at)';
            $dailyTrend = (clone $base)
                ->select(DB::raw("{$dateExpr} as date"), DB::raw('count(*) as count'), DB::raw('sum(work_orders.total) as revenue'))
                ->groupBy('date')
                ->orderBy('date')
                ->toBase()
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'count' => (int) $row->count,
                    'revenue' => round((float) ($row->revenue ?? 0), 2),
                ]);

            return [
                'status_counts' => $statusCounts,
                'service_type_counts' => $serviceTypeCounts,
                'avg_completion_hours' => round((float) $avgCompletionHours, 1),
                'month_revenue' => number_format((float) $monthRevenue, 2, '.', ''),
                'sla_compliance' => $slaCompliance,
                'overdue_orders' => $overdueOrders,
                'total_orders' => (clone $base)->count(),
                'top_customers' => $topCustomers,
                'daily_trend' => $dailyTrend,
            ];
        });

        return ApiResponse::data($data);
    }

    public function metadata(): JsonResponse
    {
        $this->authorize('viewAny', WorkOrder::class);

        return ApiResponse::data([
            'statuses' => WorkOrder::STATUSES,
            'priorities' => WorkOrder::PRIORITIES,
        ]);
    }
}
