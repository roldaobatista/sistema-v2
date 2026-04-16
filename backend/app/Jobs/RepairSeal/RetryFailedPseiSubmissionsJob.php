<?php

namespace App\Jobs\RepairSeal;

use App\Models\PseiSubmission;
use App\Services\PseiSealSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedPseiSubmissionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600; // 10 minutes max

    public function __construct()
    {
        $this->onQueue('repair-seals');
    }

    public function handle(PseiSealSubmissionService $service): void
    {
        $retryable = PseiSubmission::retryable()->get();

        Log::info("Retrying {$retryable->count()} failed PSEI submissions");

        foreach ($retryable as $submission) {
            try {
                $service->retrySubmission($submission);
            } catch (\Throwable $e) {
                Log::error("Retry failed for PSEI submission {$submission->id}: {$e->getMessage()}");
            }
        }
    }

    public function tags(): array
    {
        return ['repair-seal', 'psei-retry'];
    }
}
