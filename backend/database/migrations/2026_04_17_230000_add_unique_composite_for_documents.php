<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 5 — DATA-007 (parcial)
 *
 * Adiciona UNIQUE composto com SENTINELA de soft-delete em `customers` e
 * `suppliers` — garantia at-database de unicidade de documento por tenant
 * APENAS para registros ATIVOS (não soft-deleted). Complementa a validação
 * at-application aplicada na Wave 1B em FormRequest.
 *
 * ════════════════════════════════════════════════════════════════════════════
 * Por que sentinela em vez de UNIQUE simples (tenant_id, document_hash)
 * ════════════════════════════════════════════════════════════════════════════
 *
 * Domínio Kalibrium: é REGRA DE NEGÓCIO LEGÍTIMA que após soft-delete de um
 * cliente, outro cliente com o mesmo CPF possa ser cadastrado (ex: cliente
 * cancelou e voltou meses depois). UNIQUE simples bloquearia esse fluxo.
 *
 * Limitação do MySQL 8: UNIQUE em `(tenant_id, document_hash, deleted_at)`
 * com `deleted_at NULL` permite múltiplas rows ativas com mesmo hash, porque
 * NULLs em UNIQUE são considerados distintos (https://dev.mysql.com/doc/refman/8.0/en/create-index.html).
 *
 * Solução padrão da indústria: GENERATED COLUMN que substitui NULL por valor
 * sentinela determinístico (epoch '1970-01-01 00:00:00'). UNIQUE então cobre
 * a coluna sentinela — duas rows ativas (deleted_at NULL → epoch) colidem;
 * uma ativa + uma soft-deleted (deleted_at = NOW()) não colidem.
 *
 * ════════════════════════════════════════════════════════════════════════════
 * Por que `document_hash` (e não `document`)
 * ════════════════════════════════════════════════════════════════════════════
 *
 * `document` está cifrado at-rest com IV aleatório — UNIQUE em coluna cifrada
 * não detectaria colisão. `document_hash` (Wave 1B) é HMAC-SHA256
 * determinístico do documento normalizado, criado exatamente para permitir
 * UNIQUE e busca por igualdade.
 *
 * ════════════════════════════════════════════════════════════════════════════
 * Compatibilidade entre drivers
 * ════════════════════════════════════════════════════════════════════════════
 *
 * - MySQL 8 / MariaDB 10.5+: GENERATED COLUMN STORED + UNIQUE — suportado.
 * - SQLite: GENERATED COLUMN suportado desde 3.31; UNIQUE em generated col
 *   funciona desde que a coluna seja STORED.
 * - Postgres: usa expressão UNIQUE INDEX direta (não precisa generated col).
 *
 * Migration detecta o driver e usa SQL nativo para criar a generated column,
 * pois Laravel Schema Builder não tem API portátil para isso.
 *
 * Idempotente (regra H3): guards `hasTable`, `hasColumn`, `indexExists`.
 *
 * ════════════════════════════════════════════════════════════════════════════
 * Tolerância a duplicatas legadas em produção
 * ════════════════════════════════════════════════════════════════════════════
 *
 * Se houver rows ativas duplicadas pré-existentes, ALTER falha com "duplicate
 * entry". Migration captura e segue (NÃO bloqueia outras migrations da wave).
 * Backfill / merge é tarefa operacional separada (controller de merge já
 * existe — `CustomerMergeController`).
 */
return new class extends Migration
{
    private const TABLES = [
        'customers',
        'suppliers',
    ];

    private const SENTINEL_COLUMN = 'document_hash_active_key';

    private const INDEX_SUFFIX = 'tenant_active_document_hash_unique';

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            if (! Schema::hasColumn($table, 'document_hash')) {
                continue;
            }
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            // 1) Cria generated column sentinela se ainda não existe
            if (! Schema::hasColumn($table, self::SENTINEL_COLUMN)) {
                $this->createSentinelColumn($table, $driver);
            }

            // 2) Cria UNIQUE composto cobrindo a sentinela
            $indexName = "{$table}_".self::INDEX_SUFFIX;

            if ($this->indexExists($table, $indexName)) {
                continue;
            }

            try {
                $sentinel = self::SENTINEL_COLUMN;
                Schema::table($table, function (Blueprint $t) use ($indexName, $sentinel) {
                    $t->unique(['tenant_id', 'document_hash', $sentinel], $indexName);
                });
            } catch (Throwable $e) {
                // Duplicatas legadas em produção: NÃO bloqueia. Backfill é
                // tarefa operacional separada (CustomerMergeController existe).
                if (! $this->isDuplicateError($e) && ! $this->isAlreadyExistsError($e)) {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $indexName = "{$table}_".self::INDEX_SUFFIX;

            if ($this->indexExists($table, $indexName)) {
                try {
                    Schema::table($table, function (Blueprint $t) use ($indexName) {
                        $t->dropUnique($indexName);
                    });
                } catch (Throwable $e) {
                    // Ignora se já removido fora desta migration
                }
            }

            if (Schema::hasColumn($table, self::SENTINEL_COLUMN)) {
                $this->dropSentinelColumn($table, $driver);
            }
        }
    }

    /**
     * Cria a generated column que substitui deleted_at NULL por epoch.
     */
    private function createSentinelColumn(string $table, string $driver): void
    {
        $col = self::SENTINEL_COLUMN;

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // STORED para permitir UNIQUE; CHAR(19) cabe '1970-01-01 00:00:00'
            DB::statement(
                "ALTER TABLE `{$table}` ADD COLUMN `{$col}` CHAR(19) "
                ."GENERATED ALWAYS AS (IFNULL(CAST(`deleted_at` AS CHAR(19)), '1970-01-01 00:00:00')) STORED"
            );

            return;
        }

        if ($driver === 'sqlite') {
            // SQLite 3.31+ suporta GENERATED ALWAYS AS ... STORED
            DB::statement(
                "ALTER TABLE \"{$table}\" ADD COLUMN \"{$col}\" TEXT "
                ."GENERATED ALWAYS AS (IFNULL(\"deleted_at\", '1970-01-01 00:00:00')) STORED"
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(
                "ALTER TABLE \"{$table}\" ADD COLUMN \"{$col}\" TIMESTAMP "
                ."GENERATED ALWAYS AS (COALESCE(\"deleted_at\", TIMESTAMP '1970-01-01 00:00:00')) STORED"
            );

            return;
        }
    }

    /**
     * Remove a generated column (rollback).
     */
    private function dropSentinelColumn(string $table, string $driver): void
    {
        $col = self::SENTINEL_COLUMN;

        try {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE \"{$table}\" DROP COLUMN \"{$col}\"");
            } elseif ($driver === 'sqlite') {
                // SQLite 3.35+ suporta DROP COLUMN; algumas versões não.
                DB::statement("ALTER TABLE \"{$table}\" DROP COLUMN \"{$col}\"");
            }
        } catch (Throwable $e) {
            // Ignora — rollback best-effort
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $result = DB::select(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
                [$table, $indexName]
            );

            return count($result) > 0;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $result = DB::select(
                'SELECT INDEX_NAME FROM information_schema.STATISTICS '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$table, $indexName]
            );

            return count($result) > 0;
        }

        if ($driver === 'pgsql') {
            $result = DB::select(
                'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            );

            return count($result) > 0;
        }

        return false;
    }

    private function isAlreadyExistsError(Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'already exists')
            || str_contains($msg, 'duplicate key name')
            || str_contains($msg, 'duplicate index');
    }

    private function isDuplicateError(Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'duplicate entry')
            || str_contains($msg, 'unique constraint failed')
            || str_contains($msg, 'duplicate key value');
    }
};
