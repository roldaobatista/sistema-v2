<?php

namespace App\Services\Journey;

use App\Events\JourneyDayUpdated;
use App\Models\JourneyEntry;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JourneyOrchestratorService
{
    public function __construct(
        private TimeClassificationEngine $engine,
        private JourneyPolicyResolver $policyResolver,
    ) {}

    public function processTimeClockEvent(TimeClockEntry $entry): ?JourneyEntry
    {
        $user = User::withoutGlobalScope('tenant')->find($entry->user_id);
        if (! $user) {
            Log::warning('JourneyOrchestrator: user not found', ['user_id' => $entry->user_id]);

            return null;
        }

        $date = $entry->clock_in ? Carbon::parse($entry->clock_in)->startOfDay() : now()->startOfDay();

        return $this->processDay($user, $date, 'time_clock');
    }

    public function processWorkOrderEvent(WorkOrder $workOrder): ?JourneyEntry
    {
        if (! $workOrder->assigned_to) {
            return null;
        }

        $user = User::withoutGlobalScope('tenant')->find($workOrder->assigned_to);
        if (! $user) {
            Log::warning('JourneyOrchestrator: user not found', ['user_id' => $workOrder->assigned_to]);

            return null;
        }

        $date = $workOrder->started_at
            ? Carbon::parse($workOrder->started_at)->startOfDay()
            : now()->startOfDay();

        return $this->processDay($user, $date, 'work_order');
    }

    public function processDay(User $user, Carbon $date, string $trigger = 'manual'): ?JourneyEntry
    {
        return DB::transaction(function () use ($user, $date, $trigger) {
            $policy = $this->policyResolver->resolve($user);
            $journeyDay = $this->engine->classifyDay($user, $date, $policy);

            event(new JourneyDayUpdated($journeyDay, $trigger));

            return $journeyDay;
        });
    }

    public function reprocessDay(int $userId, Carbon $date): ?JourneyEntry
    {
        $user = User::withoutGlobalScope('tenant')->find($userId);
        if (! $user) {
            return null;
        }

        return $this->processDay($user, $date, 'reprocess');
    }
}
