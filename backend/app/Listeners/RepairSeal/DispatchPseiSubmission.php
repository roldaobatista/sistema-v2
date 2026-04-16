<?php

namespace App\Listeners\RepairSeal;

use App\Events\RepairSeal\SealUsedOnWorkOrder;
use App\Jobs\RepairSeal\SubmitSealToPseiJob;

class DispatchPseiSubmission
{
    public function handle(SealUsedOnWorkOrder $event): void
    {
        if ($event->seal->requiresPsei()) {
            SubmitSealToPseiJob::dispatch($event->seal->id)
                ->onQueue('repair-seals');
        }
    }
}
