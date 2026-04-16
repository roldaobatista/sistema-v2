<?php

namespace App\Listeners\Journey;

use App\Events\ClockEntryRegistered;
use App\Services\Journey\JourneyOrchestratorService;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnTimeClockEvent implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private JourneyOrchestratorService $orchestrator,
    ) {}

    public function handle(ClockEntryRegistered $event): void
    {
        $this->orchestrator->processTimeClockEvent($event->entry);
    }
}
