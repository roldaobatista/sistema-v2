<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreScoringRuleRequest;
use App\Http\Requests\Crm\UpdateScoringRuleRequest;
use App\Models\CrmDeal;
use App\Models\CrmForecastSnapshot;
use App\Models\CrmLeadScore;
use App\Models\CrmLeadScoringRule;
use App\Models\CrmPipeline;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmSalesPipelineController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    // ── Lead Scoring ──────────────────────────────────────

    public function scoringRules(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.scoring.view'), 403);

        return ApiResponse::paginated(
            CrmLeadScoringRule::where('tenant_id', $this->tenantId($request))->orderBy('sort_order')->paginate(50)
        );
    }

    public function storeScoringRule(StoreScoringRuleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $rule = CrmLeadScoringRule::create([...$data, 'tenant_id' => $this->tenantId($request)]);

        return ApiResponse::data($rule, 201);
    }

    public function updateScoringRule(UpdateScoringRuleRequest $request, CrmLeadScoringRule $rule): JsonResponse
    {
        $rule->update($request->validated());

        return ApiResponse::data($rule);
    }

    public function destroyScoringRule(Request $request, CrmLeadScoringRule $rule): JsonResponse
    {
        abort_unless($request->user()->can('crm.scoring.manage'), 403);

        try {
            $rule->delete();

            return ApiResponse::message('Regra removida.');
        } catch (\Exception $e) {
            Log::error('CrmSalesPipeline destroyScoringRule failed', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover regra.', 500);
        }
    }

    public function calculateScores(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.scoring.manage'), 403);

        $tenantId = $this->tenantId($request);
        $rules = CrmLeadScoringRule::where('tenant_id', $tenantId)->active()->get();

        $ruleFields = $rules->pluck('field')->unique()->filter()->all();
        $selectFields = array_unique(array_merge(['id', 'tenant_id'], $ruleFields));
        $customers = Customer::where('tenant_id', $tenantId)->where('is_active', true)->select($selectFields)->get();

        $calculated = 0;
        foreach ($customers as $customer) {
            $totalScore = 0;
            $breakdown = [];

            foreach ($rules as $rule) {
                $fieldValue = $customer->{$rule->field} ?? null;
                $matches = $this->evaluateScoringRule($rule, $fieldValue);

                if ($matches) {
                    $totalScore += $rule->points;
                    $breakdown[] = ['rule_id' => $rule->id, 'rule_name' => $rule->name, 'points' => $rule->points, 'field' => $rule->field];
                }
            }

            $grade = CrmLeadScore::calculateGrade($totalScore);
            CrmLeadScore::updateOrCreate(
                ['tenant_id' => $tenantId, 'customer_id' => $customer->id],
                ['total_score' => $totalScore, 'score_breakdown' => $breakdown, 'grade' => $grade, 'calculated_at' => now()]
            );

            $customer->update(['lead_score' => $totalScore, 'lead_grade' => $grade]);
            $calculated++;
        }

        return ApiResponse::message("Scores calculados para {$calculated} clientes");
    }

    public function leaderboard(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.scoring.view'), 403);

        return ApiResponse::paginated(
            CrmLeadScore::where('tenant_id', $this->tenantId($request))
                ->with('customer:id,name,email,phone,segment,health_score')
                ->orderByDesc('total_score')
                ->paginate(min((int) $request->input('per_page', 50), 100))
        );
    }

    // ── Forecasting ───────────────────────────────────────

    public function forecast(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.forecast.view'), 403);

        $tenantId = $this->tenantId($request);
        $periodType = $request->input('period', 'monthly');
        $months = $request->input('months', 3);

        $globalStart = now()->startOfMonth();
        $globalEnd = now()->addMonths($months - 1)->endOfMonth();

        $allOpenDeals = CrmDeal::where('tenant_id', $tenantId)
            ->open()
            ->where(function ($q) use ($globalStart, $globalEnd) {
                $q->whereBetween('expected_close_date', [$globalStart, $globalEnd])
                    ->orWhereNull('expected_close_date');
            })
            ->get();

        $historicalWinRate = $this->historicalWinRate($tenantId, 6);

        $forecast = [];
        for ($i = 0; $i < $months; $i++) {
            $start = now()->addMonths($i)->startOfMonth();
            $end = now()->addMonths($i)->endOfMonth();
            $isFirstMonth = ($i === 0);
            $openDeals = $allOpenDeals->filter(function ($deal) use ($start, $end, $isFirstMonth) {
                if ($deal->expected_close_date === null) {
                    return $isFirstMonth;
                }

                return $deal->expected_close_date >= $start && $deal->expected_close_date <= $end;
            });

            $pipelineValue = $openDeals->sum('value');
            $weightedValue = $openDeals->sum(fn ($d) => $d->value * ($d->probability / 100));
            $bestCase = $pipelineValue * min($historicalWinRate * 1.2, 1);
            $worstCase = $weightedValue * 0.7;
            $committed = $openDeals->where('probability', '>=', 80)->sum('value');

            $byStage = $openDeals->groupBy('stage_id')->map(fn ($deals) => [
                'count' => $deals->count(), 'value' => $deals->sum('value'),
                'weighted' => $deals->sum(fn ($d) => $d->value * ($d->probability / 100)),
            ]);
            $byUser = $openDeals->groupBy('assigned_to')->map(fn ($deals) => [
                'count' => $deals->count(), 'value' => $deals->sum('value'),
            ]);

            $forecast[] = [
                'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                'pipeline_value' => round($pipelineValue, 2), 'weighted_value' => round($weightedValue, 2),
                'best_case' => round($bestCase, 2), 'worst_case' => round($worstCase, 2),
                'committed' => round($committed, 2), 'deal_count' => $openDeals->count(),
                'historical_win_rate' => round($historicalWinRate * 100, 1),
                'by_stage' => $byStage, 'by_user' => $byUser,
            ];
        }

        $wonLast12 = CrmDeal::where('tenant_id', $tenantId)->won()
            ->where('won_at', '>=', now()->subMonths(12))->select('won_at', 'value')->get()
            ->filter(fn (CrmDeal $deal) => $deal->won_at !== null)
            ->groupBy(fn (CrmDeal $deal) => $deal->won_at->format('Y-m'))
            ->sortKeys()
            ->map(fn ($deals, string $month) => ['month' => $month, 'total' => round((float) $deals->sum('value'), 2), 'count' => $deals->count()])
            ->values();

        return ApiResponse::data(['forecast' => $forecast, 'historical_won' => $wonLast12, 'period_type' => $periodType]);
    }

    public function snapshotForecast(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.forecast.view'), 403);

        $tenantId = $this->tenantId($request);
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $openDeals = CrmDeal::where('tenant_id', $tenantId)->open()->select('id', 'tenant_id', 'value', 'probability', 'status', 'stage_id', 'assigned_to')->get();
        $pipelineValue = $openDeals->sum('value');
        $weightedValue = $openDeals->sum(fn ($d) => $d->value * ($d->probability / 100));
        $committed = $openDeals->where('probability', '>=', 80)->sum('value');
        $historicalWinRate = $this->historicalWinRate($tenantId, 6);
        $byStage = $openDeals->groupBy('stage_id')->map(fn ($deals) => ['count' => $deals->count(), 'value' => round((float) $deals->sum('value'), 2), 'weighted' => round((float) $deals->sum(fn ($deal) => $deal->value * ($deal->probability / 100)), 2)])->toArray();
        $byUser = $openDeals->groupBy('assigned_to')->map(fn ($deals) => ['count' => $deals->count(), 'value' => round((float) $deals->sum('value'), 2)])->toArray();

        $wonThisMonth = CrmDeal::where('tenant_id', $tenantId)->won()->where('won_at', '>=', $start)->select('id', 'value')->get();

        $snapshotDate = now()->toDateString();
        $snapshot = CrmForecastSnapshot::query()->where('tenant_id', $tenantId)->where('period_type', 'monthly')->whereDate('snapshot_date', $snapshotDate)->first() ?? new CrmForecastSnapshot(['tenant_id' => $tenantId, 'snapshot_date' => $snapshotDate, 'period_type' => 'monthly']);

        $snapshot->fill([
            'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
            'pipeline_value' => $pipelineValue, 'weighted_value' => $weightedValue,
            'best_case' => $pipelineValue * min($historicalWinRate * 1.2, 1), 'worst_case' => $weightedValue * 0.7,
            'committed' => $committed, 'deal_count' => $openDeals->count(),
            'won_value' => $wonThisMonth->sum('value'), 'won_count' => $wonThisMonth->count(),
            'by_stage' => $byStage, 'by_user' => $byUser,
        ]);
        $snapshot->save();

        return ApiResponse::data(['message' => 'Snapshot criado', 'snapshot_id' => $snapshot->id]);
    }

    // ── Pipeline Velocity ─────────────────────────────────

    public function pipelineVelocity(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.pipeline.view'), 403);

        $tenantId = $this->tenantId($request);
        $months = $request->input('months', 6);
        $pipelineId = $request->input('pipeline_id');
        $since = now()->subMonths($months);

        $wonDeals = CrmDeal::where('tenant_id', $tenantId)->won()->where('won_at', '>=', $since)
            ->when($pipelineId, fn ($q, $pid) => $q->where('pipeline_id', $pid))->get();

        $avgCycleDays = $wonDeals->avg(fn ($d) => $d->created_at->diffInDays($d->won_at));
        $avgValue = $wonDeals->avg('value');

        $stageMetrics = DB::table('crm_activities')->where('crm_activities.tenant_id', $tenantId)
            ->where('crm_activities.type', 'system')->where('crm_activities.title', 'like', '%movido%estágio%')
            ->where('crm_activities.created_at', '>=', $since)
            ->select(DB::raw('COUNT(*) as transitions'), DB::raw('AVG(duration_minutes) as avg_duration'))->first();

        $pipeline = $pipelineId
            ? CrmPipeline::with('stages')->find($pipelineId)
            : CrmPipeline::where('tenant_id', $tenantId)->active()->default()->with('stages')->first();

        $stageAnalysis = [];
        if ($pipeline) {
            foreach ($pipeline->stages as $stage) {
                $dealsInStage = CrmDeal::where('tenant_id', $tenantId)->where('pipeline_id', $pipeline->id)->where('stage_id', $stage->id)->count();
                $stageAnalysis[] = [
                    'stage_id' => $stage->id, 'stage_name' => $stage->name, 'color' => $stage->color,
                    'current_deals' => $dealsInStage,
                    'current_value' => CrmDeal::where('tenant_id', $tenantId)->where('stage_id', $stage->id)->open()->sum('value'),
                ];
            }
        }

        return ApiResponse::data([
            'avg_cycle_days' => round($avgCycleDays ?? 0, 1), 'avg_deal_value' => round($avgValue ?? 0, 2),
            'total_won' => $wonDeals->count(), 'total_won_value' => $wonDeals->sum('value'),
            'velocity' => $avgCycleDays > 0 ? round(($wonDeals->count() * ($avgValue ?? 0)) / $avgCycleDays, 2) : 0,
            'stage_analysis' => $stageAnalysis,
        ]);
    }

    // ── Cohort Analysis ───────────────────────────────────

    public function cohortAnalysis(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $months = $request->input('months', 12);

        $cohorts = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $cohortStart = now()->subMonths($i)->startOfMonth();
            $cohortEnd = now()->subMonths($i)->endOfMonth();
            $label = $cohortStart->format('Y-m');

            $created = CrmDeal::where('tenant_id', $tenantId)->whereBetween('created_at', [$cohortStart, $cohortEnd])->count();
            $conversions = [];
            for ($j = 0; $j <= min($i, 6); $j++) {
                $checkDate = $cohortStart->copy()->addMonths($j)->endOfMonth();
                $won = CrmDeal::where('tenant_id', $tenantId)->whereBetween('created_at', [$cohortStart, $cohortEnd])->won()->where('won_at', '<=', $checkDate)->count();
                $conversions["month_{$j}"] = $created > 0 ? round(($won / $created) * 100, 1) : 0;
            }

            $cohorts[] = ['cohort' => $label, 'created' => $created, 'conversions' => $conversions];
        }

        return ApiResponse::data($cohorts);
    }

    // ── Helpers ────────────────────────────────────────────

    private function evaluateScoringRule(CrmLeadScoringRule $rule, $value): bool
    {
        $ruleValue = $rule->value;

        return match ($rule->operator) {
            'equals' => (string) $value === $ruleValue,
            'not_equals' => (string) $value !== $ruleValue,
            'greater_than' => is_numeric($value) && $value > (float) $ruleValue,
            'less_than' => is_numeric($value) && $value < (float) $ruleValue,
            'contains' => str_contains((string) $value, $ruleValue),
            'in' => in_array((string) $value, explode(',', $ruleValue)),
            'not_in' => ! in_array((string) $value, explode(',', $ruleValue)),
            'between' => $this->evaluateBetween($value, $ruleValue),
            default => false,
        };
    }

    private function evaluateBetween($value, string $range): bool
    {
        $parts = explode(',', $range);
        if (count($parts) !== 2 || ! is_numeric($value)) {
            return false;
        }

        return $value >= (float) $parts[0] && $value <= (float) $parts[1];
    }

    private function historicalWinRate(int $tenantId, int $months): float
    {
        $since = now()->subMonths($months);
        $won = CrmDeal::where('tenant_id', $tenantId)->won()->where('won_at', '>=', $since)->count();
        $lost = CrmDeal::where('tenant_id', $tenantId)->lost()->where('lost_at', '>=', $since)->count();
        $total = $won + $lost;

        return $total > 0 ? $won / $total : 0.3;
    }
}
