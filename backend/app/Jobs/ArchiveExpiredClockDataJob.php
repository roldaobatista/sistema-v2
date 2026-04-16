<?php

namespace App\Jobs;

use App\Models\TimeClockEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Portaria 671 Art 96 — Política de retenção de dados.
 * Dados de ponto devem ser mantidos por 5 anos.
 * Este job marca registros antigos como arquivados (não deleta).
 */
class ArchiveExpiredClockDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $retentionYears = 5
    ) {}

    public function handle(): void
    {
        $cutoffDate = now()->subYears($this->retentionYears);

        $archived = TimeClockEntry::whereNull('archived_at')
            ->where('clock_in', '<', $cutoffDate)
            ->update(['archived_at' => now()]);

        Log::info("ArchiveExpiredClockData: {$archived} entries archived (older than {$this->retentionYears} years).");
    }
}
