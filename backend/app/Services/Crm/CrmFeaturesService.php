<?php

namespace App\Services\Crm;

use App\Models\CrmContractRenewal;
use App\Models\CrmDeal;
use App\Models\CrmDealCompetitor;
use App\Models\CrmForecastSnapshot;
use App\Models\CrmInteractiveProposal;
use App\Models\CrmLeadScore;
use App\Models\CrmLeadScoringRule;
use App\Models\CrmPipeline;
use App\Models\CrmSequence;
use App\Models\CrmSequenceEnrollment;
use App\Models\CrmSequenceStep;
use App\Models\CrmTrackingEvent;
use App\Models\CrmWebForm;
use App\Models\CrmWebFormSubmission;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Services\QuoteService;
use App\Support\SearchSanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmFeaturesService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function createTrackingEvent(
        int $tenantId,
        string $trackableType,
        int $trackableId,
        string $eventType,
        ?int $customerId = null,
        ?int $dealId = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        CrmTrackingEvent::create([
            'tenant_id' => $tenantId,
            'trackable_type' => $trackableType,
            'trackable_id' => $trackableId,
            'customer_id' => $customerId,
            'deal_id' => $dealId,
            'event_type' => $eventType,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array{calculated: int}
     */
    public function calculateScores(int $tenantId): array
    {
        $rules = CrmLeadScoringRule::where('tenant_id', $tenantId)->active()->get();
        if ($rules->isEmpty()) {
            return ['calculated' => 0];
        }

        /** @var array<string> $ruleFields */
        $ruleFields = $rules->pluck('field')->unique()->filter()->all();
        $selectFields = array_unique(array_merge(['id', 'tenant_id'], $ruleFields));
        $customers = Customer::where('tenant_id', $tenantId)->where('is_active', true)->select($selectFields)->get();

        $calculated = 0;
        foreach ($customers as $customer) {
            $totalScore = 0;
            $breakdown = [];

            foreach ($rules as $rule) {
                /** @var string $field */
                $field = $rule->field;
                /** @var mixed $fieldValue */
                $fieldValue = $customer->{$field} ?? null;
                $matches = $this->evaluateScoringRule($rule, $fieldValue);

                if ($matches) {
                    $totalScore += $rule->points;
                    $breakdown[] = [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name,
                        'points' => $rule->points,
                        'field' => $rule->field,
                    ];
                }
            }

            $grade = CrmLeadScore::calculateGrade($totalScore);

            CrmLeadScore::updateOrCreate(
                ['tenant_id' => $tenantId, 'customer_id' => $customer->id],
                [
                    'total_score' => $totalScore,
                    'score_breakdown' => $breakdown,
                    'grade' => $grade,
                    'calculated_at' => now(),
                ]
            );

            $customer->update(['lead_score' => $totalScore, 'lead_grade' => $grade]);
            $calculated++;
        }

        return ['calculated' => $calculated];
    }

    private function evaluateScoringRule(CrmLeadScoringRule $rule, mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        /** @var string $conditionValue */
        $conditionValue = $rule->condition_value ?? '';

        return match ($rule->operator) {
            'equals' => (string) $value === $conditionValue,
            'not_equals' => (string) $value !== $conditionValue,
            'greater_than' => (float) $value > (float) $conditionValue,
            'less_than' => (float) $value < (float) $conditionValue,
            'contains' => mb_stripos((string) $value, $conditionValue) !== false,
            'between' => $this->evaluateBetween($value, $conditionValue),
            default => false,
        };
    }

    private function evaluateBetween(mixed $value, string $condition): bool
    {
        $parts = explode(',', $condition);
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            return (float) $value >= (float) $parts[0] && (float) $value <= (float) $parts[1];
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeSequence(int $tenantId, array $data, int $userId): CrmSequence
    {
        return DB::transaction(function () use ($tenantId, $data, $userId) {
            $sequence = CrmSequence::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'created_by' => $userId,
            ]);

            if (! empty($data['steps'])) {
                /** @var array<int, array<string, mixed>> $steps */
                $steps = $data['steps'];
                foreach ($steps as $idx => $step) {
                    CrmSequenceStep::create([
                        'sequence_id' => $sequence->id,
                        'name' => $step['name'],
                        'action_type' => $step['action_type'],
                        'step_order' => $idx + 1,
                        'delay_days' => $step['delay_days'] ?? 0,
                        'template_id' => $step['template_id'] ?? null,
                        'assigned_to' => $step['assigned_to'] ?? null,
                        'content' => $step['content'] ?? null,
                    ]);
                }
            }

            return $sequence;
        });
    }

    /**
     * @param  array<int>  $customerIds
     * @return array{enrolled: int, skipped: int}
     */
    public function enrollInSequence(int $tenantId, int $sequenceId, array $customerIds, int $userId): array
    {
        return DB::transaction(function () use ($tenantId, $sequenceId, $customerIds, $userId) {
            $sequence = CrmSequence::where('tenant_id', $tenantId)->findOrFail($sequenceId);
            /** @var CrmSequenceStep|null $firstStep */
            $firstStep = $sequence->steps()->orderBy('step_order')->first();

            $enrolled = 0;
            $skipped = 0;

            foreach ($customerIds as $customerId) {
                $exists = CrmSequenceEnrollment::where('tenant_id', $tenantId)
                    ->where('sequence_id', $sequence->id)
                    ->where('customer_id', $customerId)
                    ->whereIn('status', ['active', 'paused'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $delayDays = $firstStep ? (int) $firstStep->delay_days : 0;
                $stepId = $firstStep ? $firstStep->id : null;

                CrmSequenceEnrollment::create([
                    'tenant_id' => $tenantId,
                    'sequence_id' => $sequence->id,
                    'customer_id' => $customerId,
                    'current_step_id' => $stepId,
                    'status' => 'active',
                    'next_action_at' => now()->addDays($delayDays),
                    'enrolled_by' => $userId,
                ]);
                $enrolled++;
            }

            return ['enrolled' => $enrolled, 'skipped' => $skipped];
        });
    }

    /**
     * @return array{actual_won_period: float, historical_win_rate: float, forecast: array<int, array<string, mixed>>}
     */
    public function forecast(int $tenantId, string $periodType, int $months): array
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->addMonths($months)->endOfMonth();

        $openDeals = CrmDeal::where('tenant_id', $tenantId)
            ->open()
            ->whereNotNull('expected_close_date')
            ->whereBetween('expected_close_date', [$startDate, $endDate])
            ->select('id', 'value', 'probability', 'expected_close_date', 'stage_id')
            ->get();

        $actualWon = (float) CrmDeal::where('tenant_id', $tenantId)
            ->won()
            ->whereBetween('won_at', [$startDate, $endDate])
            ->sum('value');

        $historicalWinRate = $this->historicalWinRate($tenantId);

        $forecast = [];
        for ($i = 0; $i < $months; $i++) {
            $periodStart = now()->addMonths($i)->startOfMonth();
            $periodEnd = now()->addMonths($i)->endOfMonth();

            $dealsInPeriod = $openDeals->filter(fn ($d) => clone $d->expected_close_date >= $periodStart && clone $d->expected_close_date <= $periodEnd);

            $weightedValue = 0.0;
            $bestCase = 0.0;
            $commit = 0.0;

            foreach ($dealsInPeriod as $deal) {
                // Se a probabilidade no deal for manual (ex: > 0) e não null, usar
                $prob = $deal->probability ?? $historicalWinRate;
                $value = (float) $deal->value;
                $weightedValue += $value * ($prob / 100);

                $bestCase += $value; // Considera todos fechando 100%

                if ($prob >= 80) {
                    $commit += $value;
                }
            }

            $label = match ($periodType) {
                'quarterly' => 'Q'.ceil($periodStart->month / 3).' '.$periodStart->year,
                'yearly' => (string) $periodStart->year,
                default => $periodStart->format('Y-m'),
            };

            if (! isset($forecast[$label])) {
                $forecast[$label] = [
                    'label' => $label,
                    'weighted_pipeline' => 0.0,
                    'best_case' => 0.0,
                    'commit' => 0.0,
                ];
            }

            $forecast[$label]['weighted_pipeline'] += $weightedValue;
            $forecast[$label]['best_case'] += $bestCase;
            $forecast[$label]['commit'] += $commit;
        }

        return [
            'actual_won_period' => $actualWon,
            'historical_win_rate' => $historicalWinRate,
            'forecast' => array_values($forecast),
        ];
    }

    private function historicalWinRate(int $tenantId): float
    {
        $lastYear = now()->subYear();
        $totalResolved = CrmDeal::where('tenant_id', $tenantId)
            ->whereIn('status', ['won', 'lost'])
            ->where('updated_at', '>=', $lastYear)
            ->count();

        if ($totalResolved === 0) {
            return 30.0;
        } // default assumptions

        $won = CrmDeal::where('tenant_id', $tenantId)
            ->won()
            ->where('won_at', '>=', $lastYear)
            ->count();

        return round(($won / $totalResolved) * 100, 2);
    }

    public function createSnapshotForecast(int $tenantId, int $userId): CrmForecastSnapshot
    {
        $data = $this->forecast($tenantId, 'monthly', 12);

        $totalPipeline = (float) CrmDeal::where('tenant_id', $tenantId)->open()->sum('value');
        /** @var array<int, array<string, mixed>> $forecastData */
        $forecastData = $data['forecast'];

        $weightedPipeline = collect($forecastData)->sum('weighted_pipeline');
        $closeWon = (float) CrmDeal::where('tenant_id', $tenantId)->won()
            ->whereBetween('won_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('value');

        return CrmForecastSnapshot::create([
            'tenant_id' => $tenantId,
            'snapshot_date' => now(),
            'period_type' => 'monthly',
            'period_label' => now()->format('Y-m'),
            'total_pipeline_value' => $totalPipeline,
            'weighted_pipeline_value' => $weightedPipeline,
            'closed_won_value' => $closeWon,
            'forecast_data' => $forecastData,
            'created_by' => $userId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCrossSellRecommendations(int $tenantId, int $customerId, Customer $customer): array
    {
        $recommendations = [];

        // Equipamentos não calibrados
        $customerEquipCount = Equipment::where('customer_id', $customerId)->count();
        $calibratedCount = Equipment::where('customer_id', $customerId)
            ->whereNotNull('last_calibration_at')
            ->count();

        if ($customerEquipCount > $calibratedCount) {
            $uncalibrated = $customerEquipCount - $calibratedCount;
            $recommendations[] = [
                'type' => 'cross_sell',
                'title' => "Calibrar {$uncalibrated} equipamento(s) pendente(s)",
                'description' => "Cliente possui {$customerEquipCount} equipamentos mas apenas {$calibratedCount} são calibrados regularmente.",
                'estimated_value' => $uncalibrated * 150,
                'priority' => 'high',
            ];
        }

        // Upgrade para contrato
        if (empty($customer->contract_type) || $customer->contract_type === 'avulso') {
            $annualSpend = CrmDeal::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->won()
                ->where('won_at', '>=', now()->subYear())
                ->sum('value');

            if ($annualSpend > 1000) {
                $recommendations[] = [
                    'type' => 'up_sell',
                    'title' => 'Propor contrato anual',
                    'description' => 'Cliente gasta R$ '.number_format((float) $annualSpend, 2, ',', '.').'/ano em serviços avulsos. Contrato anual pode economizar 15-20%.',
                    'estimated_value' => $annualSpend * 0.85,
                    'priority' => 'high',
                ];
            }
        }

        // Serviços similares a clientes do mesmo segmento
        $segment = $customer->segment;
        if ($segment) {
            $popularServices = CrmDeal::where('tenant_id', $tenantId)
                ->whereHas('customer', fn ($q) => $q->where('segment', $segment)->where('id', '!=', $customerId))
                ->won()
                ->where('won_at', '>=', now()->subYear())
                ->select('source', DB::raw('COUNT(*) as cnt'), DB::raw('AVG(value) as avg_value'))
                ->groupBy('source')
                ->orderByDesc('cnt')
                ->limit(3)
                ->get();

            // Pre-load customer's existing won deal sources to avoid N+1
            $customerExistingSources = CrmDeal::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->won()
                ->whereNotNull('source')
                ->pluck('source')
                ->unique();

            foreach ($popularServices as $svc) {
                $customerHas = $customerExistingSources->contains($svc->source);
                $sameSegmentCustomers = (int) $svc->getAttribute('cnt');
                $averageValue = (float) $svc->getAttribute('avg_value');

                if (! $customerHas && $svc->source) {
                    $recommendations[] = [
                        'type' => 'cross_sell',
                        'title' => 'Serviço popular no segmento: '.(CrmDeal::SOURCES[$svc->source] ?? $svc->source),
                        'description' => "{$sameSegmentCustomers} clientes do mesmo segmento utilizam este serviço.",
                        'estimated_value' => round($averageValue, 2),
                        'priority' => 'medium',
                    ];
                }
            }
        }

        return $recommendations;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLossAnalytics(int $tenantId, int $months): array
    {
        $since = now()->subMonths($months);

        try {
            $byReason = CrmDeal::where('crm_deals.tenant_id', $tenantId)
                ->where('crm_deals.status', CrmDeal::STATUS_LOST)
                ->where('crm_deals.lost_at', '>=', $since)
                ->whereNotNull('crm_deals.loss_reason_id')
                ->whereNull('crm_deals.deleted_at')
                ->join('crm_loss_reasons', 'crm_deals.loss_reason_id', '=', 'crm_loss_reasons.id')
                ->select('crm_loss_reasons.name', 'crm_loss_reasons.category',
                    DB::raw('COUNT(*) as count'), DB::raw('SUM(crm_deals.value) as total_value'))
                ->groupBy('crm_loss_reasons.name', 'crm_loss_reasons.category')
                ->orderByDesc('count')
                ->get();

            $byCompetitor = CrmDeal::where('crm_deals.tenant_id', $tenantId)
                ->lost()
                ->where('lost_at', '>=', $since)
                ->whereNotNull('competitor_name')
                ->select('competitor_name',
                    DB::raw('COUNT(*) as count'), DB::raw('SUM(value) as total_value'),
                    DB::raw('AVG(competitor_price) as avg_competitor_price'))
                ->groupBy('competitor_name')
                ->orderByDesc('count')
                ->get();

            $byUser = CrmDeal::where('crm_deals.tenant_id', $tenantId)
                ->where('crm_deals.status', CrmDeal::STATUS_LOST)
                ->where('crm_deals.lost_at', '>=', $since)
                ->whereNull('crm_deals.deleted_at')
                ->join('users', 'crm_deals.assigned_to', '=', 'users.id')
                ->select('users.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(crm_deals.value) as total_value'))
                ->groupBy('users.name')
                ->orderByDesc('count')
                ->get();

            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $monthExpr = $isSqlite ? "strftime('%Y-%m', lost_at)" : "DATE_FORMAT(lost_at, '%Y-%m')";

            $monthlyTrend = CrmDeal::where('tenant_id', $tenantId)
                ->lost()
                ->where('lost_at', '>=', $since)
                ->selectRaw("{$monthExpr} as month, COUNT(*) as count, SUM(value) as total_value")
                ->groupByRaw($monthExpr)
                ->orderBy('month')
                ->get();
        } catch (\Exception $e) {
            Log::warning('CrmFeatures lossAnalytics query failed', ['error' => $e->getMessage()]);

            return [
                'by_reason' => [],
                'by_competitor' => [],
                'by_user' => [],
                'monthly_trend' => [],
            ];
        }

        return [
            'by_reason' => $byReason,
            'by_competitor' => $byCompetitor,
            'by_user' => $byUser,
            'monthly_trend' => $monthlyTrend,
        ];
    }

    /** @return array<string, mixed> */
    public function getPipelineVelocity(int $tenantId, int $months, ?int $pipelineId): array
    {
        $since = now()->subMonths($months);

        $wonDeals = CrmDeal::where('tenant_id', $tenantId)
            ->won()
            ->where('won_at', '>=', $since)
            ->when($pipelineId, fn ($q, $pid) => $q->where('pipeline_id', $pid))
            ->get();

        $avgCycleDays = $wonDeals->avg(fn ($d) => $d->created_at->diffInDays($d->won_at));
        $avgValue = $wonDeals->avg('value');

        // Stage duration from activities
        $stageMetrics = DB::table('crm_activities')
            ->where('crm_activities.tenant_id', $tenantId)
            ->where('crm_activities.type', 'system')
            ->where('crm_activities.title', 'like', '%movido%estágio%')
            ->where('crm_activities.created_at', '>=', $since)
            ->select(
                DB::raw('COUNT(*) as transitions'),
                DB::raw('AVG(duration_minutes) as avg_duration')
            )
            ->first();

        // Win rate by stage
        $pipeline = $pipelineId
            ? CrmPipeline::with('stages')->find($pipelineId)
            : CrmPipeline::where('tenant_id', $tenantId)->active()->default()->with('stages')->first();

        $stageAnalysis = [];
        if ($pipeline) {
            foreach ($pipeline->stages as $stage) {
                $dealsInStage = CrmDeal::where('tenant_id', $tenantId)
                    ->where('pipeline_id', $pipeline->id)
                    ->where('stage_id', $stage->id)
                    ->count();

                $dealsPassedThrough = CrmDeal::where('tenant_id', $tenantId)
                    ->where('pipeline_id', $pipeline->id)
                    ->won()
                    ->where('won_at', '>=', $since)
                    ->count();

                $stageAnalysis[] = [
                    'stage_id' => $stage->id,
                    'stage_name' => $stage->name,
                    'color' => $stage->color,
                    'current_deals' => $dealsInStage,
                    'current_value' => CrmDeal::where('tenant_id', $tenantId)
                        ->where('stage_id', $stage->id)->open()->sum('value'),
                ];
            }
        }

        return [
            'avg_cycle_days' => round((float) ($avgCycleDays ?? 0), 1),
            'avg_deal_value' => round((float) ($avgValue ?? 0), 2),
            'total_won' => $wonDeals->count(),
            'total_won_value' => $wonDeals->sum('value'),
            'velocity' => $avgCycleDays > 0
                ? round(($wonDeals->count() * (float) ($avgValue ?? 0)) / $avgCycleDays, 2)
                : 0,
            'stage_analysis' => $stageAnalysis,
        ];
    }

    public function generateRenewals(int $tenantId): int
    {
        $generated = 0;

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('contract_end')
            ->where('contract_end', '<=', now()->addDays(90))
            ->where('contract_end', '>=', now())
            ->get();

        foreach ($customers as $customer) {
            $exists = CrmContractRenewal::where('tenant_id', $tenantId)
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['pending', 'notified', 'in_negotiation'])
                ->exists();

            if (! $exists) {
                $lastDealValue = CrmDeal::where('customer_id', $customer->id)
                    ->won()
                    ->latest('won_at')
                    ->value('value') ?? 0;

                $renewal = CrmContractRenewal::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'contract_end_date' => $customer->contract_end,
                    'current_value' => $lastDealValue,
                    'status' => 'pending',
                ]);

                // Auto-create deal
                $defaultPipeline = CrmPipeline::where('tenant_id', $tenantId)->default()->first();
                if ($defaultPipeline) {
                    $firstStage = $defaultPipeline->stages()->orderBy('sort_order')->first();
                    if ($firstStage) {
                        $deal = CrmDeal::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'pipeline_id' => $defaultPipeline->id,
                            'stage_id' => $firstStage->id,
                            'title' => "Renovação - {$customer->name}",
                            'value' => $lastDealValue,
                            'source' => 'contrato_renovacao',
                            'assigned_to' => $customer->assigned_seller_id,
                            'expected_close_date' => $customer->contract_end,
                        ]);
                        $renewal->update(['deal_id' => $deal->id]);
                    }
                }

                $generated++;
            }
        }

        return $generated;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function processWebForm(
        CrmWebForm $form,
        array $data,
        ?string $ipAddress,
        ?string $userAgent
    ): CrmWebFormSubmission {
        return DB::transaction(function () use ($form, $data, $ipAddress, $userAgent) {
            $email = $data['email'] ?? null;
            $phone = preg_replace('/[^0-9]/', '', $data['phone'] ?? $data['whatsapp'] ?? $data['telefone'] ?? '');
            if (empty($phone)) {
                $phone = null;
            }

            // Find or create customer
            $customer = null;
            $assignedSellerId = $form->assign_to;

            if ($email) {
                $customer = Customer::where('tenant_id', $form->tenant_id)
                    ->where('email', $email)
                    ->first();
            }

            if (! $customer && $phone) {
                $customer = Customer::where('tenant_id', $form->tenant_id)
                    ->where('phone', $phone)
                    ->first();
            }

            if (! $customer && ($email || $phone)) {
                $customer = Customer::create([
                    'tenant_id' => $form->tenant_id,
                    'name' => $data['name'] ?? $data['nome'] ?? 'Lead Web Form',
                    'email' => $email,
                    'phone' => $phone,
                    'source' => 'web_form',
                    'assigned_seller_id' => $assignedSellerId,
                ]);
            }

            // Create deal
            $deal = null;
            if ($form->pipeline_id && $customer) {
                $pipeline = CrmPipeline::query()
                    ->where('tenant_id', $form->tenant_id)
                    ->find($form->pipeline_id);
                $firstStage = $pipeline?->stages()->orderBy('sort_order')->first();

                if ($pipeline && $firstStage) {
                    $deal = CrmDeal::create([
                        'tenant_id' => $form->tenant_id,
                        'customer_id' => $customer->id,
                        'pipeline_id' => $pipeline->id,
                        'stage_id' => $firstStage->id,
                        'title' => 'Lead via formulário: '.($customer->name ?? 'Web'),
                        'source' => 'prospeccao',
                        'assigned_to' => $assignedSellerId,
                    ]);
                }
            }

            // Enroll in sequence
            if ($form->sequence_id && $customer) {
                $sequence = CrmSequence::query()
                    ->where('tenant_id', $form->tenant_id)
                    ->find($form->sequence_id);
                $firstStep = $sequence?->steps()->orderBy('step_order')->first();

                if ($sequence) {
                    CrmSequenceEnrollment::create([
                        'tenant_id' => $form->tenant_id,
                        'sequence_id' => $sequence->id,
                        'customer_id' => $customer->id,
                        'deal_id' => $deal?->id,
                        'next_action_at' => now()->addDays($firstStep->delay_days ?? 0),
                    ]);
                }
            }

            $submission = CrmWebFormSubmission::create([
                'form_id' => $form->id,
                'customer_id' => $customer?->id,
                'deal_id' => $deal?->id,
                'data' => $data,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
            ]);

            $form->increment('submissions_count');

            $this->createTrackingEvent(
                tenantId: $form->tenant_id,
                trackableType: CrmWebFormSubmission::class,
                trackableId: $submission->id,
                eventType: 'form_submitted',
                customerId: $customer?->id,
                dealId: $deal?->id,
                metadata: [
                    'form_id' => $form->id,
                    'form_slug' => $form->slug,
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return $submission;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function processProposalResponse(string $token, array $data): string
    {
        return DB::transaction(function () use ($data, $token) {
            $proposal = CrmInteractiveProposal::query()
                ->where('token', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $proposal->canReceiveResponse()) {
                throw new \DomainException('Proposta ja respondida ou indisponivel.');
            }

            $accepted = $data['action'] === 'accept';

            $proposal->update([
                'status' => $accepted
                    ? CrmInteractiveProposal::STATUS_ACCEPTED
                    : CrmInteractiveProposal::STATUS_REJECTED,
                'client_notes' => $data['client_notes'] ?? null,
                'client_signature' => $data['client_signature'] ?? null,
                'item_interactions' => $data['item_interactions'] ?? null,
                'accepted_at' => $accepted ? now() : null,
                'rejected_at' => $accepted ? null : now(),
            ]);

            if ($accepted && $proposal->quote_id) {
                $quote = Quote::query()
                    ->where('tenant_id', $proposal->tenant_id)
                    ->find($proposal->quote_id);

                if (! $quote) {
                    throw new \DomainException('Orcamento vinculado nao encontrado.');
                }

                app(QuoteService::class)->publicApprove($quote);
            }

            $proposal->loadMissing('quote:id,customer_id');

            $this->createTrackingEvent(
                tenantId: $proposal->tenant_id,
                trackableType: CrmInteractiveProposal::class,
                trackableId: $proposal->id,
                eventType: $accepted ? 'proposal_accepted' : 'proposal_rejected',
                customerId: $proposal->quote instanceof Quote ? $proposal->quote->customer_id : null,
                dealId: $proposal->deal_id,
            );

            return $accepted ? 'Proposta aceita!' : 'Proposta recusada';
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function getCohortAnalysis(int $tenantId, int $months): array
    {
        $cohorts = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $cohortStart = now()->subMonths($i)->startOfMonth();
            $cohortEnd = now()->subMonths($i)->endOfMonth();
            $label = $cohortStart->format('Y-m');

            $created = CrmDeal::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$cohortStart, $cohortEnd])
                ->count();

            $conversions = [];
            for ($j = 0; $j <= min($i, 6); $j++) {
                $checkDate = $cohortStart->copy()->addMonths($j)->endOfMonth();
                $won = CrmDeal::where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$cohortStart, $cohortEnd])
                    ->won()
                    ->where('won_at', '<=', $checkDate)
                    ->count();

                $conversions["month_{$j}"] = $created > 0 ? round(($won / $created) * 100, 1) : 0;
            }

            $cohorts[] = [
                'cohort' => $label,
                'created' => $created,
                'conversions' => $conversions,
            ];
        }

        return $cohorts;
    }

    /** @return array<string, mixed> */
    public function getRevenueIntelligence(int $tenantId): array
    {
        // MRR from active contracts
        $contractCustomers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('contract_type')
            ->where('contract_type', '!=', 'avulso')
            ->count();

        $mrr = CrmDeal::where('tenant_id', $tenantId)
            ->won()
            ->whereHas('customer', fn ($q) => $q->whereNotNull('contract_type')->where('contract_type', '!=', 'avulso'))
            ->where('won_at', '>=', now()->subYear())
            ->avg('value') ?? 0;

        // One-time revenue
        $oneTimeRevenue = CrmDeal::where('tenant_id', $tenantId)
            ->won()
            ->where('won_at', '>=', now()->startOfMonth())
            ->whereHas('customer', fn ($q) => $q->where(function ($q2) {
                $q2->whereNull('contract_type')->orWhere('contract_type', 'avulso');
            }))
            ->sum('value');

        // Churn rate
        $totalActiveStart = Customer::where('tenant_id', $tenantId)
            ->where('created_at', '<=', now()->subMonth()->startOfMonth())
            ->where('is_active', true)
            ->count();

        $churned = Customer::where('tenant_id', $tenantId)
            ->where('is_active', false)
            ->where('updated_at', '>=', now()->subMonth()->startOfMonth())
            ->count();

        $churnRate = $totalActiveStart > 0 ? round(($churned / $totalActiveStart) * 100, 1) : 0;

        // LTV
        $avgDealValue = CrmDeal::where('tenant_id', $tenantId)->won()->avg('value') ?? 0;
        $avgDealsPerCustomer = CrmDeal::where('tenant_id', $tenantId)->won()
            ->select('customer_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('customer_id')
            ->get()
            ->avg('cnt') ?? 1;

        $ltv = bcmul((string) $avgDealValue, (string) $avgDealsPerCustomer, 2);

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "strftime('%Y-%m', won_at)" : "DATE_FORMAT(won_at, '%Y-%m')";

        $monthlyRevenue = CrmDeal::where('tenant_id', $tenantId)
            ->won()
            ->where('won_at', '>=', now()->subMonths(12))
            ->selectRaw("{$monthExpr} as month, SUM(value) as revenue, COUNT(*) as deals")
            ->groupByRaw($monthExpr)
            ->orderBy('month')
            ->get();

        // By segment
        $bySegment = CrmDeal::where('crm_deals.tenant_id', $tenantId)
            ->where('crm_deals.status', 'won')
            ->whereNotNull('crm_deals.won_at')
            ->where('crm_deals.won_at', '>=', now()->subYear())
            ->whereNull('crm_deals.deleted_at')
            ->join('customers', 'crm_deals.customer_id', '=', 'customers.id')
            ->select('customers.segment', DB::raw('SUM(crm_deals.value) as revenue'), DB::raw('COUNT(*) as deals'))
            ->groupBy('customers.segment')
            ->orderByDesc('revenue')
            ->get();

        return [
            'mrr' => round((float) $mrr, 2),
            'contract_customers' => $contractCustomers,
            'one_time_revenue' => round((float) $oneTimeRevenue, 2),
            'churn_rate' => $churnRate,
            'ltv' => round((float) $ltv, 2),
            'avg_deal_value' => round((float) $avgDealValue, 2),
            'monthly_revenue' => $monthlyRevenue,
            'by_segment' => $bySegment,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, CrmDealCompetitor>|Collection<int, CrmDealCompetitor>
     */
    public function getCompetitiveMatrix(int $tenantId, int $months, bool $detailed, int $perPage)
    {
        $since = now()->subMonths($months);

        if ($detailed) {
            return CrmDealCompetitor::query()
                ->whereHas('deal', function ($query) use ($tenantId, $since) {
                    $query->where('tenant_id', $tenantId)
                        ->where('created_at', '>=', $since)
                        ->whereNull('deleted_at');
                })
                ->with(['deal:id,title,value,status,customer_id', 'deal.customer:id,name'])
                ->orderByDesc('created_at')
                ->paginate($perPage);
        }

        return CrmDealCompetitor::join('crm_deals', 'crm_deal_competitors.deal_id', '=', 'crm_deals.id')
            ->where('crm_deals.tenant_id', $tenantId)
            ->where('crm_deals.created_at', '>=', $since)
            ->whereNull('crm_deals.deleted_at')
            ->select(
                'crm_deal_competitors.competitor_name',
                DB::raw('COUNT(*) as total_encounters'),
                DB::raw('SUM(CASE WHEN crm_deals.status = "won" THEN 1 ELSE 0 END) as wins'),
                DB::raw('SUM(CASE WHEN crm_deals.status = "lost" THEN 1 ELSE 0 END) as losses'),
                DB::raw('AVG(crm_deal_competitors.competitor_price) as avg_price'),
                DB::raw('AVG(crm_deals.value) as our_avg_price'),
            )
            ->groupBy('crm_deal_competitors.competitor_name')
            ->orderByDesc('total_encounters')
            ->get()
            ->map(function ($c) {
                $wins = (int) $c->getAttribute('wins');
                $losses = (int) $c->getAttribute('losses');
                $avgPrice = (float) $c->getAttribute('avg_price');
                $ourAvgPrice = (float) $c->getAttribute('our_avg_price');
                $total = $wins + $losses;

                $c->setAttribute('win_rate', $total > 0 ? round(($wins / $total) * 100, 1) : 0);
                $c->setAttribute(
                    'price_diff',
                    $avgPrice > 0 && $ourAvgPrice > 0
                        ? round((($ourAvgPrice - $avgPrice) / $avgPrice) * 100, 1)
                        : null
                );

                return $c;
            });
    }

    public function exportDealsCsvCallback(int $tenantId, ?int $pipelineId, ?string $status): callable
    {
        $query = CrmDeal::where('tenant_id', $tenantId)
            ->with(['customer:id,name', 'pipeline:id,name', 'stage:id,name', 'assignee:id,name']);

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $deals = $query->orderByDesc('created_at')->get();

        $headers = ['ID', 'Título', 'Cliente', 'Pipeline', 'Etapa', 'Valor', 'Probabilidade', 'Status', 'Origem', 'Responsável', 'Previsão Fechamento', 'Criado em'];

        return function () use ($deals, $headers) {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                throw new \RuntimeException('Não foi possível abrir o stream de saída CSV.');
            }

            fprintf($file, '%s', chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($file, $headers, ';');

            foreach ($deals as $deal) {
                fputcsv($file, [
                    (int) $deal->id,
                    (string) $deal->title,
                    $deal->customer->name ?? '',
                    $deal->pipeline->name ?? '',
                    $deal->stage->name ?? '',
                    number_format((float) ($deal->value ?? 0), 2, ',', '.'),
                    (int) ($deal->probability ?? 0),
                    $deal->status,
                    (string) ($deal->source ?? ''),
                    $deal->assignee->name ?? '',
                    $deal->expected_close_date?->format('d/m/Y') ?? '',
                    $deal->created_at?->format('d/m/Y H:i') ?? '',
                ], ';');
            }

            fclose($file);
        };
    }

    /** @return array{imported: int, errors: array<int, string>} */
    public function importDealsCsv(int $tenantId, int $userId, UploadedFile $file): array
    {
        $imported = 0;
        $errors = [];

        $handle = fopen($file->getPathname(), 'r');
        if ($handle === false) {
            return ['imported' => 0, 'errors' => ['Arquivo CSV não pôde ser aberto']];
        }

        $headerRow = fgetcsv($handle, 0, ';');

        if (! $headerRow) {
            fclose($handle);

            return ['imported' => 0, 'errors' => ['Arquivo CSV vazio ou malformado']];
        }

        $headerMap = array_flip(array_map(static fn ($value): string => mb_strtolower(trim((string) $value)), $headerRow));
        $row = 1;

        $defaultPipeline = CrmPipeline::where('tenant_id', $tenantId)->default()->first();
        $firstStage = $defaultPipeline?->stages()->orderBy('sort_order')->first();

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $row++;

            try {
                $title = trim($data[$headerMap['título'] ?? $headerMap['titulo'] ?? $headerMap['title'] ?? 0] ?? '');
                if (! $title) {
                    $errors[] = "Linha {$row}: título obrigatório";
                    continue;
                }

                $customerName = trim($data[$headerMap['cliente'] ?? $headerMap['customer'] ?? 99] ?? '');
                $customer = null;
                if ($customerName) {
                    $safeCustomerName = SearchSanitizer::contains($customerName);
                    $customer = Customer::where('tenant_id', $tenantId)
                        ->where('name', 'like', $safeCustomerName)
                        ->first();
                }

                if (! $customer) {
                    $errors[] = "Linha {$row}: cliente '{$customerName}' não encontrado";
                    continue;
                }

                $valueStr = trim($data[$headerMap['valor'] ?? $headerMap['value'] ?? 99] ?? '0');
                $value = (float) str_replace(['.', ','], ['', '.'], $valueStr);

                CrmDeal::create([
                    'tenant_id' => $tenantId,
                    'title' => $title,
                    'customer_id' => $customer->id,
                    'pipeline_id' => $defaultPipeline?->id,
                    'stage_id' => $firstStage?->id,
                    'value' => $value,
                    'source' => trim($data[$headerMap['origem'] ?? $headerMap['source'] ?? 99] ?? '') ?: null,
                    'assigned_to' => $userId,
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Linha {$row}: ".$e->getMessage();
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'errors' => array_slice($errors, 0, 20),
        ];
    }
}
