<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\CorrectiveAction;
use App\Models\QualityCorrectiveAction;
use App\Models\QualityProcedure;
use App\Models\SatisfactionSurvey;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class QualityAnalyticsController extends Controller
{
    use ResolvesCurrentTenant;

    public function analyticsQuality(Request $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $period = min(max((int) $request->integer('period', 6), 1), 24);
        $startDate = now()->subMonths($period)->startOfMonth();

        $totalProcedures = QualityProcedure::query()
            ->where('tenant_id', $tenantId)
            ->count();

        $activeProcedures = QualityProcedure::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $conformityIndex = $totalProcedures > 0
            ? round(($activeProcedures / $totalProcedures) * 100, 1)
            : 0;

        $npsTrend = SatisfactionSurvey::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('nps_score')
            ->where('created_at', '>=', $startDate)
            ->get(['nps_score', 'created_at'])
            ->groupBy(fn (SatisfactionSurvey $survey): string => $survey->created_at?->format('Y-m') ?? now()->format('Y-m'))
            ->map(function ($group, string $month): array {
                $total = $group->count();
                $promoters = $group->where('nps_score', '>=', 9)->count();
                $detractors = $group->where('nps_score', '<=', 6)->count();

                return [
                    'month' => $month,
                    'total' => $total,
                    'promoters' => $promoters,
                    'detractors' => $detractors,
                    'nps' => $total > 0 ? round((($promoters - $detractors) / $total) * 100, 1) : 0,
                ];
            })
            ->values();

        $generalActions = CorrectiveAction::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['closed', 'completed', 'cancelled'])
            ->get(['deadline', 'created_at']);

        $auditActions = QualityCorrectiveAction::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [QualityCorrectiveAction::STATUS_COMPLETED, QualityCorrectiveAction::STATUS_VERIFIED])
            ->get(['due_date', 'created_at'])
            ->map(function (QualityCorrectiveAction $action): array {
                return [
                    'deadline' => $action->due_date,
                    'created_at' => $action->created_at,
                ];
            });

        $openActions = $generalActions
            ->map(fn (CorrectiveAction $action): array => [
                'deadline' => $action->deadline,
                'created_at' => $action->created_at,
            ])
            ->concat($auditActions)
            ->values();
        /** @var Collection<int, array{deadline: mixed, created_at: mixed}> $openActions */

        return ApiResponse::data([
            'conformity_index' => $conformityIndex,
            'nps_trend' => $npsTrend,
            'actions_aging' => $this->buildActionsAging($openActions),
        ]);
    }

    /**
     * @param  Collection<int, array{deadline: mixed, created_at: mixed}>  $openActions
     * @return array{total_open: int, overdue: int, due_7_days: int}
     */
    private function buildActionsAging(Collection $openActions): array
    {
        $today = now()->startOfDay();

        return [
            'total_open' => $openActions->count(),
            'overdue' => $openActions->filter(function (array $action) use ($today): bool {
                return $action['deadline'] !== null && $action['deadline'] < $today;
            })->count(),
            'due_7_days' => $openActions->filter(function (array $action) use ($today): bool {
                return $action['deadline'] !== null
                    && $action['deadline'] >= $today
                    && $action['deadline'] <= $today->copy()->addDays(7);
            })->count(),
        ];
    }
}
