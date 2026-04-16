<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\TimeClockEntry;
use App\Models\Training;
use App\Models\User;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HRAnalyticsController extends Controller
{
    use ResolvesCurrentTenant;

    public function analyticsHr(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $period = min(max((int) $request->integer('period', 3), 1), 24);
        $startDate = now()->subMonths($period)->startOfMonth();
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        $diffMinutesExpr = $isSqlite
            ? '(CAST((julianday(clock_out) - julianday(clock_in)) * 1440 AS INTEGER))'
            : 'TIMESTAMPDIFF(MINUTE, clock_in, clock_out)';

        $timeExpr = $isSqlite
            ? "strftime('%H:%M:%S', clock_in)"
            : 'TIME(clock_in)';

        $monthExpr = $isSqlite
            ? "strftime('%Y-%m', clock_in)"
            : "DATE_FORMAT(clock_in, '%Y-%m')";

        $overtimeRanking = DB::table('time_clock_entries')
            ->join('users', 'time_clock_entries.user_id', '=', 'users.id')
            ->where('time_clock_entries.tenant_id', $tenantId)
            ->whereNotNull('clock_out')
            ->where('clock_in', '>=', $startDate)
            ->groupBy('users.id', 'users.name')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(*) as total_entries'),
                DB::raw("ROUND(SUM({$diffMinutesExpr}) / 60, 1) as total_hours")
            )
            ->orderByDesc('total_hours')
            ->limit(10)
            ->get();

        $punctuality = TimeClockEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('clock_in', '>=', $startDate)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN {$timeExpr} <= '08:15:00' THEN 1 ELSE 0 END) as on_time")
            ->selectRaw("SUM(CASE WHEN {$timeExpr} > '08:15:00' THEN 1 ELSE 0 END) as late")
            ->first();

        $hoursTrend = DB::table('time_clock_entries')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('clock_out')
            ->where('clock_in', '>=', $startDate)
            ->groupBy(DB::raw($monthExpr))
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw("ROUND(SUM({$diffMinutesExpr}) / 60, 1) as total_hours"),
                DB::raw('COUNT(DISTINCT user_id) as unique_employees')
            )
            ->orderBy('month')
            ->get();

        $trainingStats = Training::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->first();

        $employeeCount = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        return ApiResponse::data([
            'overtime_ranking' => $overtimeRanking,
            'punctuality' => $punctuality,
            'hours_trend' => $hoursTrend,
            'training_stats' => $trainingStats,
            'employee_count' => $employeeCount,
            'period_months' => $period,
        ]);
    }
}
