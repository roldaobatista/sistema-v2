<?php

namespace App\Events\RepairSeal;

use App\Models\InmetroSeal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SealUsedOnWorkOrder
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly InmetroSeal $seal,
        public readonly int $workOrderId,
        public readonly int $equipmentId,
        public readonly int $userId,
    ) {}
}
