<?php

namespace App\Events\RepairSeal;

use App\Models\RepairSealBatch;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SealBatchReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RepairSealBatch $batch,
    ) {}
}
