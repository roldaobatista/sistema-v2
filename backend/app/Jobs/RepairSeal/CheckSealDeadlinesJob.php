<?php

namespace App\Jobs\RepairSeal;

use App\Services\RepairSealDeadlineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSealDeadlinesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300; // 5 minutes max

    public function __construct()
    {
        $this->onQueue('repair-seals');
    }

    public function handle(RepairSealDeadlineService $service): void
    {
        Log::info('Starting seal deadline check job');

        $stats = $service->checkAllDeadlines();

        Log::info('Seal deadline check completed', $stats);
    }

    public function tags(): array
    {
        return ['repair-seal', 'deadline-check'];
    }
}
