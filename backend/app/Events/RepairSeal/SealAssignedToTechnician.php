<?php

namespace App\Events\RepairSeal;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SealAssignedToTechnician
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $technicianId,
        public readonly array $sealIds,
        public readonly int $assignedBy,
    ) {}
}
