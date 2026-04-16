<?php

namespace App\Services\Journey;

use App\Enums\TimeClassificationType;
use App\Models\JourneyBlock;
use App\Models\JourneyEntry;
use App\Models\JourneyRule;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * @phpstan-type TimelineEvent array{
 *     type: string,
 *     at: mixed,
 *     source_type: string,
 *     source_id: int|string|null,
 *     work_order_id?: int|string|null,
 *     metadata?: array<string, mixed>
 * }
 * @phpstan-type TimelineSegment array{
 *     started_at: Carbon,
 *     ended_at: Carbon|null,
 *     duration_minutes: int,
 *     event: TimelineEvent,
 *     next_event: TimelineEvent|null
 * }
 * @phpstan-type JourneyBlockPayload array{
 *     classification: string,
 *     started_at: Carbon,
 *     ended_at: Carbon|null,
 *     duration_minutes: int,
 *     work_order_id: int|string|null,
 *     time_clock_entry_id: int|string|null,
 *     fleet_trip_id: int|string|null,
 *     source: string,
 *     metadata: array<string, mixed>|null,
 *     is_auto_classified: bool,
 *     is_manually_adjusted: bool
 * }
 */
class TimeClassificationEngine
{
    public function __construct(
        private JourneyPolicyResolver $policyResolver,
    ) {}

    public function classifyDay(User $user, Carbon $date, ?JourneyRule $rule = null): JourneyEntry
    {
        $rule ??= $this->policyResolver->resolve($user);

        /** @var Collection<int, TimelineEvent> $events */
        $events = $this->collectAllEvents($user, $date);
        /** @var Collection<int, TimelineSegment> $timeline */
        $timeline = $this->buildTimeline($events, $date);
        /** @var Collection<int, JourneyBlockPayload> $blocks */
        $blocks = $this->classifyBlocks($timeline, $rule, $date);

        $journeyEntry = $this->upsertJourneyEntry($user, $date, $rule);
        $this->syncBlocks($journeyEntry, $blocks, $user);
        $journeyEntry->recalculateTotals();

        return $journeyEntry->fresh(['blocks']);
    }

    /**
     * @return Collection<int, TimelineEvent>
     */
    private function collectAllEvents(User $user, Carbon $date): Collection
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $events = collect();

        // Time clock entries (batidas de ponto)
        $clockEntries = TimeClockEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->current_tenant_id)
            ->where('user_id', $user->id)
            ->where(function ($q) use ($startOfDay, $endOfDay) {
                $q->whereBetween('clock_in', [$startOfDay, $endOfDay])
                    ->orWhereBetween('clock_out', [$startOfDay, $endOfDay]);
            })
            ->orderBy('clock_in')
            ->get();

        foreach ($clockEntries as $entry) {
            $events->push([
                'type' => 'clock_in',
                'at' => $entry->clock_in,
                'source_type' => 'time_clock_entry',
                'source_id' => $entry->id,
                'work_order_id' => $entry->work_order_id,
                'metadata' => [
                    'has_break' => $entry->break_start !== null,
                    'break_start' => $entry->break_start?->toISOString(),
                    'break_end' => $entry->break_end?->toISOString(),
                ],
            ]);

            if ($entry->break_start) {
                $events->push([
                    'type' => 'break_start',
                    'at' => $entry->break_start,
                    'source_type' => 'time_clock_entry',
                    'source_id' => $entry->id,
                ]);
            }

            if ($entry->break_end) {
                $events->push([
                    'type' => 'break_end',
                    'at' => $entry->break_end,
                    'source_type' => 'time_clock_entry',
                    'source_id' => $entry->id,
                ]);
            }

            if ($entry->clock_out) {
                $events->push([
                    'type' => 'clock_out',
                    'at' => $entry->clock_out,
                    'source_type' => 'time_clock_entry',
                    'source_id' => $entry->id,
                ]);
            }
        }

        // Work order events (check-ins/check-outs de OS)
        $workOrders = WorkOrder::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->current_tenant_id)
            ->where('assigned_to', $user->id)
            ->where(function ($q) use ($startOfDay, $endOfDay) {
                $q->whereBetween('started_at', [$startOfDay, $endOfDay])
                    ->orWhereBetween('completed_at', [$startOfDay, $endOfDay]);
            })
            ->orderBy('started_at')
            ->get();

        foreach ($workOrders as $wo) {
            if ($wo->started_at) {
                $events->push([
                    'type' => 'os_checkin',
                    'at' => $wo->started_at,
                    'source_type' => 'work_order',
                    'source_id' => $wo->id,
                    'work_order_id' => $wo->id,
                ]);
            }

            if ($wo->completed_at) {
                $events->push([
                    'type' => 'os_checkout',
                    'at' => $wo->completed_at,
                    'source_type' => 'work_order',
                    'source_id' => $wo->id,
                    'work_order_id' => $wo->id,
                ]);
            }
        }

        return $events->sortBy('at')->values();
    }

    /**
     * @param  Collection<int, mixed>  $events
     * @return Collection<int, TimelineSegment>
     */
    private function buildTimeline(Collection $events, Carbon $date): Collection
    {
        if ($events->isEmpty()) {
            return collect();
        }

        $timeline = collect();
        /** @var array<int, TimelineEvent> $eventsArray */
        $eventsArray = $events->values()->all();

        for ($i = 0; $i < count($eventsArray); $i++) {
            $current = $eventsArray[$i];
            $next = $eventsArray[$i + 1] ?? null;

            $startAt = Carbon::parse($current['at']);
            $endAt = $next ? Carbon::parse($next['at']) : null;

            $timeline->push([
                'started_at' => $startAt,
                'ended_at' => $endAt,
                'duration_minutes' => $endAt ? (int) $startAt->diffInMinutes($endAt) : 0,
                'event' => $current,
                'next_event' => $next,
            ]);
        }

        return $timeline;
    }

    /**
     * @param  Collection<int, mixed>  $timeline
     * @return Collection<int, JourneyBlockPayload>
     */
    private function classifyBlocks(Collection $timeline, JourneyRule $policy, Carbon $date): Collection
    {
        $blocks = collect();
        $totalWorkedMinutes = 0;

        foreach ($timeline as $segment) {
            /** @var TimelineSegment $segment */
            $classification = $this->resolveClassification(
                $segment,
                $policy,
                $date,
                $totalWorkedMinutes,
            );

            $isWorkType = in_array($classification, [
                TimeClassificationType::JORNADA_NORMAL,
                TimeClassificationType::EXECUCAO_SERVICO,
                TimeClassificationType::HORA_EXTRA,
                TimeClassificationType::ADICIONAL_NOTURNO,
            ]);

            if ($isWorkType) {
                $totalWorkedMinutes += $segment['duration_minutes'];
            }

            // Check if daily limit exceeded — reclassify as overtime
            if ($classification === TimeClassificationType::JORNADA_NORMAL
                && $totalWorkedMinutes > $policy->daily_hours_limit) {
                $excessMinutes = $totalWorkedMinutes - $policy->daily_hours_limit;
                $normalMinutes = $segment['duration_minutes'] - $excessMinutes;

                if ($normalMinutes > 0) {
                    $blocks->push($this->buildBlock($segment, TimeClassificationType::JORNADA_NORMAL, $normalMinutes, $policy));
                }

                if ($excessMinutes > 0) {
                    $overtimeSegment = $segment;
                    $overtimeSegment['started_at'] = $segment['started_at']->copy()->addMinutes($normalMinutes);
                    $overtimeSegment['duration_minutes'] = $excessMinutes;
                    $blocks->push($this->buildBlock($overtimeSegment, TimeClassificationType::HORA_EXTRA, $excessMinutes, $policy));
                }

                continue;
            }

            $blocks->push($this->buildBlock($segment, $classification, $segment['duration_minutes'], $policy));
        }

        return $blocks;
    }

    /**
     * @param  TimelineSegment  $segment
     */
    private function resolveClassification(array $segment, JourneyRule $policy, Carbon $date, int $totalWorkedMinutes): TimeClassificationType
    {
        $event = $segment['event'];
        $nextEvent = $segment['next_event'] ?? null;

        // Break events
        if ($event['type'] === 'break_start') {
            return TimeClassificationType::INTERVALO;
        }

        // OS check-in: execution
        if ($event['type'] === 'os_checkin') {
            return TimeClassificationType::EXECUCAO_SERVICO;
        }

        // After OS checkout, before next event: displacement between
        if ($event['type'] === 'os_checkout' && $nextEvent) {
            if ($nextEvent['type'] === 'os_checkin') {
                return $policy->displacement_counts_as_work
                    ? TimeClassificationType::JORNADA_NORMAL
                    : TimeClassificationType::DESLOCAMENTO_ENTRE;
            }

            if ($nextEvent['type'] === 'clock_out') {
                return $policy->displacement_counts_as_work
                    ? TimeClassificationType::JORNADA_NORMAL
                    : TimeClassificationType::DESLOCAMENTO_CLIENTE;
            }
        }

        // Clock in, before OS check-in: displacement to client
        if ($event['type'] === 'clock_in' && $nextEvent) {
            if ($nextEvent['type'] === 'os_checkin') {
                return $policy->displacement_counts_as_work
                    ? TimeClassificationType::JORNADA_NORMAL
                    : TimeClassificationType::DESLOCAMENTO_CLIENTE;
            }
        }

        // After break: resume normal work
        if ($event['type'] === 'break_end') {
            return TimeClassificationType::JORNADA_NORMAL;
        }

        // Overtime day (saturday/sunday per policy)
        if ($policy->isOvertimeDay($date)) {
            return TimeClassificationType::HORA_EXTRA;
        }

        // Default: normal work
        if (in_array($event['type'], ['clock_in', 'clock_out'])) {
            return TimeClassificationType::JORNADA_NORMAL;
        }

        return TimeClassificationType::JORNADA_NORMAL;
    }

    /**
     * @param  TimelineSegment  $segment
     * @return JourneyBlockPayload
     */
    private function buildBlock(array $segment, TimeClassificationType $classification, int $durationMinutes, JourneyRule $policy): array
    {
        $source = match ($segment['event']['source_type']) {
            'time_clock_entry' => 'clock',
            'work_order' => 'os_checkin',
            'fleet_trip' => 'displacement',
            default => 'manual',
        };

        return [
            'classification' => $classification->value,
            'started_at' => $segment['started_at'],
            'ended_at' => $segment['ended_at'],
            'duration_minutes' => $durationMinutes,
            'work_order_id' => $segment['event']['work_order_id'] ?? null,
            'time_clock_entry_id' => $segment['event']['source_type'] === 'time_clock_entry'
                ? $segment['event']['source_id']
                : null,
            'fleet_trip_id' => $segment['event']['source_type'] === 'fleet_trip'
                ? $segment['event']['source_id']
                : null,
            'source' => $source,
            'metadata' => $segment['event']['metadata'] ?? null,
            'is_auto_classified' => true,
            'is_manually_adjusted' => false,
        ];
    }

    private function upsertJourneyEntry(User $user, Carbon $date, JourneyRule $rule): JourneyEntry
    {
        $existing = JourneyEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->current_tenant_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $date->format('Y-m-d'))
            ->first();

        if ($existing) {
            $existing->update([
                'regime_type' => $rule->regime_type ?? 'clt_mensal',
                'journey_rule_id' => $rule->id,
            ]);

            return $existing;
        }

        return JourneyEntry::withoutGlobalScope('tenant')->create([
            'tenant_id' => $user->current_tenant_id,
            'user_id' => $user->id,
            'date' => $date->format('Y-m-d'),
            'journey_rule_id' => $rule->id,
            'regime_type' => $rule->regime_type ?? 'clt_mensal',
            'status' => 'calculated',
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $blocks
     */
    private function syncBlocks(JourneyEntry $journeyEntry, Collection $blocks, User $user): void
    {
        // Remove old auto-classified blocks (preserve manual adjustments)
        JourneyBlock::withoutGlobalScope('tenant')
            ->where('journey_entry_id', $journeyEntry->id)
            ->where('is_auto_classified', true)
            ->delete();

        // Create new blocks
        foreach ($blocks as $blockData) {
            /** @var JourneyBlockPayload $blockData */
            JourneyBlock::withoutGlobalScope('tenant')->create([
                ...$blockData,
                'tenant_id' => $journeyEntry->tenant_id,
                'journey_entry_id' => $journeyEntry->id,
                'user_id' => $user->id,
            ]);
        }
    }
}
