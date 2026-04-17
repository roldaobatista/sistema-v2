<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SEC-023 (Wave 1D) — Backfill assíncrono das colunas `*_hash` para PII encrypted.
 *
 * Substitui o backfill síncrono que rodava dentro da migration
 * `2026_04_17_120000_add_document_hash_for_encrypted_search`. Backfill em
 * deploy bloqueia o release proporcional ao volume de linhas (lock + tráfego),
 * o que viola o critério de "deploys curtos" no plano operacional.
 *
 * Estratégia:
 *  - Itera em chunks de 500 (`chunkById`) sobre linhas com `source_column`
 *    preenchida e `hash_column` nula.
 *  - Calcula HMAC-SHA256 com APP_KEY do valor "raw" e atualiza a linha.
 *  - Heurística de pular: se `source_column` já tem aspecto de payload
 *    encrypted (>100 chars + base64), ignora — significa que o cast já está
 *    ativo e a coluna foi gravada cifrada (backfill é pré-cast).
 *
 * Disparado via `php artisan kalibrium:backfill-document-hash` pós-deploy.
 */
class BackfillDocumentHashJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $table,
        public string $sourceColumn,
        public string $hashColumn
    ) {}

    public function handle(): void
    {
        $appKey = (string) config('app.key');
        $processed = 0;
        $skipped = 0;

        DB::table($this->table)
            ->whereNotNull($this->sourceColumn)
            ->whereNull($this->hashColumn)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($appKey, &$processed, &$skipped): void {
                foreach ($rows as $row) {
                    $value = (string) $row->{$this->sourceColumn};

                    if ($value === '') {
                        continue;
                    }

                    // Heurística: se já parece encrypted (>100 chars + base64), pular.
                    // Crypt::encryptString gera base64 longo — não dá para hashear o ciphertext.
                    if (strlen($value) > 100 && preg_match('/^[A-Za-z0-9+\/=]+$/', $value)) {
                        $skipped++;

                        continue;
                    }

                    DB::table($this->table)
                        ->where('id', $row->id)
                        ->update([
                            $this->hashColumn => hash_hmac('sha256', $value, $appKey),
                        ]);

                    $processed++;
                }
            });

        Log::info('[BackfillDocumentHashJob] concluído', [
            'table' => $this->table,
            'source' => $this->sourceColumn,
            'hash' => $this->hashColumn,
            'processed' => $processed,
            'skipped_already_encrypted' => $skipped,
        ]);
    }
}
