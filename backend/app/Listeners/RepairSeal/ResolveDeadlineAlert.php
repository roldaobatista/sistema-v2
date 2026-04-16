<?php

namespace App\Listeners\RepairSeal;

use App\Events\RepairSeal\SealPseiSubmitted;
use App\Services\RepairSealDeadlineService;

class ResolveDeadlineAlert
{
    public function __construct(
        private readonly RepairSealDeadlineService $deadlineService,
    ) {}

    public function handle(SealPseiSubmitted $event): void
    {
        $this->deadlineService->resolveDeadline(
            $event->seal,
            $event->submission->submitted_by ?? 0,
        );
    }
}
