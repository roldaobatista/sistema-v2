<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\CommissionEventStatus;
use App\Http\Controllers\Controller;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionDashboardController extends Controller
{
    use ResolvesCurrentTenant;

    public function overview(): JsonResponse
    {
        $this->authorize('viewAny', CommissionEvent::class);

        try {
            $tenantId = $this->tenantId();
            $now = now();
            $lastMonth = $now->copy()->subMonth();

            $pending = CommissionEvent::where('tenant_id', $tenantId)
                ->where('status', CommissionEventStatus::PENDING)
                ->sum('commission_amount');

            $approved = CommissionEvent::where('tenant_id', $tenantId)
                ->where('status', CommissionEventStatus::APPROVED)
                ->sum('commission_amount');

            $paidThisMonth = CommissionEvent::where('tenant_id', $tenantId)
                ->where('status', CommissionEventStatus::PAID)
                ->whereMonth('updated_at', $now->month)
                ->whereYear('updated_at', $now->year)
                ->sum('commission_amount');

            $paidLastMonth = CommissionEvent::where('tenant_id', $tenantId)
                ->where('status', CommissionEventStatus::PAID)
                ->whereMonth('updated_at', $lastMonth->month)
                ->whereYear('updated_at', $lastMonth->year)
                ->sum('commission_amount');

            $variation = null;
            if (bccomp((string) $paidLastMonth, '0', 2) > 0) {
                $diff = bcsub((string) $paidThisMonth, (string) $paidLastMonth, 2);
                $variation = (float) bcmul(bcdiv($diff, (string) $paidLastMonth, 4), '100', 1);
            } elseif (bccomp((string) $paidThisMonth, '0', 2) > 0) {
                $variation = 100.0;
            }

            $totalEvents = CommissionEvent::where('tenant_id', $tenantId)->count();
            $totalRules = CommissionRule::where('tenant_id', $tenantId)
                ->where('active', true)
                ->count();

            return ApiResponse::data([
                'pending' => (float) $pending,
                'approved' => (float) $approved,
                'paid_this_month' => (float) $paidThisMonth,
                'paid_last_month' => (float) $paidLastMonth,
                'variation_pct' => $variation,
                'total_events' => $totalEvents,
                'events_count' => $totalEvents,
                'total_rules' => $totalRules,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Falha ao carregar overview do dashboard de comissoes', [
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::message('Erro ao carregar overview.', 500);
        }
    }

    public function ranking(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommissionEvent::class);

        try {
            $tenantId = $this->tenantId();
            $period = (string) $request->get('period', now()->format('Y-m'));

            $driver = DB::getDriverName();
            $periodFilter = $driver === 'sqlite'
                ? "strftime('%Y-%m', commission_events.created_at) = ?"
                : "DATE_FORMAT(commission_events.created_at, '%Y-%m') = ?";

            $ranking = DB::table('commission_events')
                ->join('users', 'commission_events.user_id', '=', 'users.id')
                ->where('commission_events.tenant_id', $tenantId)
                ->whereIn('commission_events.status', [CommissionEventStatus::APPROVED->value, CommissionEventStatus::PAID->value])
                ->whereRaw($periodFilter, [$period])
                ->selectRaw('users.id, users.name, SUM(commission_events.commission_amount) as total, COUNT(*) as events_count')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(function ($row, int $index) {
                    $row->position = $index + 1;
                    $row->medal = match ($index) {
                        0 => '🥇',
                        1 => '🥈',
                        2 => '🥉',
                        default => null,
                    };

                    return $row;
                });

            return ApiResponse::data($ranking);
        } catch (\Throwable $exception) {
            Log::error('Falha ao carregar ranking de comissoes', [
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::message('Erro ao carregar ranking.', 500);
        }
    }

    public function evolution(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommissionEvent::class);

        try {
            $tenantId = $this->tenantId();
            $months = min((int) $request->get('months', 6), 12);
            $startDate = now()->subMonths($months - 1)->startOfMonth();

            $driver = DB::getDriverName();
            $periodExpression = $driver === 'sqlite'
                ? "strftime('%Y-%m', created_at)"
                : "DATE_FORMAT(created_at, '%Y-%m')";

            $rows = CommissionEvent::where('tenant_id', $tenantId)
                ->whereIn('status', [CommissionEventStatus::APPROVED, CommissionEventStatus::PAID])
                ->where('created_at', '>=', $startDate)
                ->selectRaw("{$periodExpression} as period, SUM(commission_amount) as total")
                ->groupByRaw($periodExpression)
                ->orderBy('period')
                ->get()
                ->keyBy('period');

            $data = [];
            for ($index = $months - 1; $index >= 0; $index--) {
                $date = now()->subMonths($index);
                $period = $date->format('Y-m');

                $data[] = [
                    'period' => $period,
                    'label' => $date->translatedFormat('M/Y'),
                    'total' => (float) ($rows[$period]->total ?? 0),
                ];
            }

            return ApiResponse::data($data);
        } catch (\Throwable $exception) {
            Log::error('Falha ao carregar evolucao de comissoes', [
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::message('Erro ao carregar evolucao.', 500);
        }
    }

    public function byRule(): JsonResponse
    {
        $this->authorize('viewAny', CommissionEvent::class);

        try {
            $tenantId = $this->tenantId();

            $distribution = DB::table('commission_events')
                ->join('commission_rules', 'commission_events.commission_rule_id', '=', 'commission_rules.id')
                ->where('commission_events.tenant_id', $tenantId)
                ->selectRaw('commission_rules.calculation_type, SUM(commission_events.commission_amount) as total, COUNT(*) as count')
                ->groupBy('commission_rules.calculation_type')
                ->orderByDesc('total')
                ->get();

            return ApiResponse::data($distribution);
        } catch (\Throwable $exception) {
            Log::error('Falha ao carregar distribuicao por regra', [
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::message('Erro ao carregar distribuicao.', 500);
        }
    }

    public function byRole(): JsonResponse
    {
        $this->authorize('viewAny', CommissionEvent::class);

        try {
            $tenantId = $this->tenantId();

            $distribution = DB::table('commission_events')
                ->join('commission_rules', 'commission_events.commission_rule_id', '=', 'commission_rules.id')
                ->where('commission_events.tenant_id', $tenantId)
                ->selectRaw('commission_rules.applies_to_role as role, SUM(commission_events.commission_amount) as total, COUNT(*) as count')
                ->groupBy('commission_rules.applies_to_role')
                ->orderByDesc('total')
                ->get();

            return ApiResponse::data($distribution);
        } catch (\Throwable $exception) {
            Log::error('Falha ao carregar distribuicao por papel', [
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::message('Erro ao carregar distribuicao.', 500);
        }
    }
}
