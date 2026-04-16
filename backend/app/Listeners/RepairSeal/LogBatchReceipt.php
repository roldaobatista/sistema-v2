<?php

namespace App\Listeners\RepairSeal;

use App\Events\RepairSeal\SealBatchReceived;
use Illuminate\Support\Facades\Log;

class LogBatchReceipt
{
    public function handle(SealBatchReceived $event): void
    {
        $batch = $event->batch;

        Log::info("Seal batch received: {$batch->batch_code}", [
            'batch_id' => $batch->id,
            'type' => $batch->type,
            'quantity' => $batch->quantity,
            'range' => "{$batch->range_start}-{$batch->range_end}",
            'tenant_id' => $batch->tenant_id,
            'received_by' => $batch->received_by,
        ]);
    }
}
