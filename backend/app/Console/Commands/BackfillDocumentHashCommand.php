<?php

namespace App\Console\Commands;

use App\Jobs\BackfillDocumentHashJob;
use Illuminate\Console\Command;

/**
 * SEC-023 (Wave 1D) — Dispara backfill assíncrono das colunas `*_hash`.
 *
 * Uso pós-deploy:
 *   php artisan kalibrium:backfill-document-hash
 *
 * O comando despacha um Job por tabela (customers, suppliers, users,
 * employee_dependents) para a fila default. Cada job processa em chunks
 * de 500 linhas, aplicando HMAC-SHA256(APP_KEY) sobre o valor raw.
 *
 * Pré-condição: rodar APÓS a migration que adiciona as colunas `*_hash`
 * (`2026_04_17_120000_add_document_hash_for_encrypted_search`).
 */
class BackfillDocumentHashCommand extends Command
{
    protected $signature = 'kalibrium:backfill-document-hash {--sync : Roda os jobs sincronamente em vez de enfileirar}';

    protected $description = 'Backfill document_hash/cpf_hash em tabelas com PII encrypted (assíncrono via fila)';

    /**
     * Mapa: tabela => [coluna_origem, coluna_hash].
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private array $targets = [
        ['customers', 'document', 'document_hash'],
        ['suppliers', 'document', 'document_hash'],
        ['users', 'cpf', 'cpf_hash'],
        ['employee_dependents', 'cpf', 'cpf_hash'],
    ];

    public function handle(): int
    {
        $sync = (bool) $this->option('sync');

        foreach ($this->targets as [$table, $source, $hash]) {
            if ($sync) {
                $this->info("Executando inline: $table.$source -> $hash");
                (new BackfillDocumentHashJob($table, $source, $hash))->handle();
            } else {
                BackfillDocumentHashJob::dispatch($table, $source, $hash);
                $this->info("Job despachado: $table.$source -> $hash");
            }
        }

        $this->newLine();
        $this->info($sync
            ? 'Backfill executado inline. Verifique logs para contagens.'
            : 'Jobs despachados. Acompanhe pela fila / Horizon.');

        return self::SUCCESS;
    }
}
