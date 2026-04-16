<?php

namespace App\Jobs\RepairSeal;

use App\Models\InmetroSeal;
use App\Services\PseiSealSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubmitSealToPseiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 1800]; // 1min, 5min, 30min

    public function __construct(
        public readonly int $sealId,
    ) {
        $this->onQueue('repair-seals');
    }

    public function handle(PseiSealSubmissionService $service): void
    {
        $seal = InmetroSeal::find($this->sealId);

        if (! $seal || $seal->psei_status === InmetroSeal::PSEI_CONFIRMED) {
            return; // Already submitted or seal not found
        }

        $service->submitSeal($seal);
    }

    public function tags(): array
    {
        return ['repair-seal', 'psei', "seal:{$this->sealId}"];
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("PSEI submission job permanently failed for seal {$this->sealId}", [
            'exception' => $exception->getMessage(),
        ]);

        $seal = InmetroSeal::find($this->sealId);
        if ($seal) {
            $seal->update(['psei_status' => InmetroSeal::PSEI_FAILED]);
        }
    }
}
