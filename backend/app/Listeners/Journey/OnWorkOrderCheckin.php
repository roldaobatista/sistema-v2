<?php

namespace App\Listeners\Journey;

use App\Models\WorkOrder;
use App\Services\Journey\JourneyOrchestratorService;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnWorkOrderCheckin implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private JourneyOrchestratorService $orchestrator,
    ) {}

    public function handle(object $event): void
    {
        if (! isset($event->workOrder) || ! $event->workOrder instanceof WorkOrder) {
            return;
        }

        $this->orchestrator->processWorkOrderEvent($event->workOrder);
    }
}
