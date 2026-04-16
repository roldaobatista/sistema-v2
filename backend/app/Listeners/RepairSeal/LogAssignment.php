<?php

namespace App\Listeners\RepairSeal;

use App\Events\RepairSeal\SealAssignedToTechnician;
use Illuminate\Support\Facades\Log;

class LogAssignment
{
    public function handle(SealAssignedToTechnician $event): void
    {
        Log::info("Seals assigned to technician {$event->technicianId}", [
            'seal_ids' => $event->sealIds,
            'count' => count($event->sealIds),
            'assigned_by' => $event->assignedBy,
        ]);
    }
}
