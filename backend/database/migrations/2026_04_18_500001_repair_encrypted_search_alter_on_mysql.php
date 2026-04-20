<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair migration para 2026_04_17_120000_add_document_hash_for_encrypted_search.
 *
 * A migration original faz `ALTER TABLE ... MODIFY document TEXT` sem dropar
 * antes o indice legado `(tenant_id, document)`. MySQL/MariaDB rejeita com
 * "1170 BLOB/TEXT column 'document' used in key specification without a key
 * length" — o indice bloqueia a conversao para TEXT.
 *
 * SQLite nao tem essa restricao (varchar e TEXT sao equivalentes), por isso a
 * original passa em suite de testes (SQLite in-memory). A regressao so aparece
 * quando se tenta aplicar a migration em MySQL/MariaDB.
 *
 * Esta migration e de REPARO e idempotente:
 *  1. Garante colunas *_hash nas 4 tabelas alvo (customers, suppliers, users,
 *     employee_dependents) — completa o step 1 da original se tiver faltado.
 *  2. Garante indice composto (tenant_id, *_hash) — step 2 da original.
 *  3. Dropa o indice legado (tenant_id, <source>) que bloqueia o ALTER.
 *  4. Executa o ALTER ... MODIFY TEXT pendente em MySQL/MariaDB.
 *
 * Safe para rodar em ambiente limpo (no-op), parcial (completa o que falta) ou
 * ja-aplicado (no-op). Guardada por hasTable/hasColumn/indexExists e por
 * verificacao explicita do DATA_TYPE antes do ALTER.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{source: string, hash: string, hash_index: string, legacy_index: string|null}>
     */
    private array $targets = [
        'customers' => [
            'source' => 'document',
            'hash' => 'document_hash',
            'hash_index' => 'customers_tenant_document_hash_idx',
            'legacy_index' => 'customers_tenant_id_document_index',
        ],
        'suppliers' => [
            'source' => 'document',
            'hash' => 'document_hash',
            'hash_index' => 'suppliers_tenant_document_hash_idx',
            'legacy_index' => 'suppliers_tenant_id_document_index',
        ],
        'users' => [
            'source' => 'cpf',
            'hash' => 'cpf_hash',
            'hash_index' => 'users_tenant_cpf_hash_idx',
            'legacy_index' => null,
        ],
        'employee_dependents' => [
            'source' => 'cpf',
            'hash' => 'cpf_hash',
            'hash_index' => 'employee_dependents_tenant_cpf_hash_idx',
            'legacy_index' => null,
        ],
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        foreach ($this->targets as $table => $cfg) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, $cfg['source']) && ! Schema::hasColumn($table, $cfg['hash'])) {
                Schema::table($table, function (Blueprint $blueprint) use ($cfg): void {
                    $blueprint->string($cfg['hash'], 64)->nullable()->after($cfg['source']);
                });
            }

            if (
                Schema::hasColumn($table, 'tenant_id')
                && Schema::hasColumn($table, $cfg['hash'])
                && ! $this->indexExists($table, $cfg['hash_index'])
            ) {
                Schema::table($table, function (Blueprint $blueprint) use ($cfg): void {
                    $blueprint->index(['tenant_id', $cfg['hash']], $cfg['hash_index']);
                });
            }

            if ($cfg['legacy_index'] !== null && $this->indexExists($table, $cfg['legacy_index'])) {
                Schema::table($table, function (Blueprint $blueprint) use ($cfg): void {
                    $blueprint->dropIndex($cfg['legacy_index']);
                });
            }

            if (in_array($driver, ['mysql', 'mariadb'], true) && Schema::hasColumn($table, $cfg['source'])) {
                if (! $this->columnIsText($table, $cfg['source'])) {
                    DB::statement(sprintf('ALTER TABLE `%s` MODIFY `%s` TEXT NULL', $table, $cfg['source']));
                }
            }
        }
    }

    public function down(): void
    {
        // No-op seguro: migration de reparo. Reversao manual se necessario.
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = collect(Schema::getConnection()
                ->getSchemaBuilder()
                ->getIndexes($table));

            return $indexes->contains(fn ($idx) => $idx['name'] === $index);
        } catch (Throwable) {
            return false;
        }
    }

    private function columnIsText(string $table, string $column): bool
    {
        try {
            $result = DB::selectOne(
                'SELECT LOWER(DATA_TYPE) AS data_type FROM information_schema.columns
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );

            return $result && $result->data_type === 'text';
        } catch (Throwable) {
            return false;
        }
    }
};
