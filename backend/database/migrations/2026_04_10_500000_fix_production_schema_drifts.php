<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration corretiva de schema drifts historicos detectados em producao
 * durante auditoria de 2026-04-10 (pos-deploy das 22 migrations novas).
 *
 * Drifts detectados via comparacao do dump regenerado de prod com o git HEAD
 * e posterior validacao direta via DESCRIBE/SHOW INDEX em producao:
 *
 * 1. auto_assignment_rules.company_id -> tenant_id
 * 2. central_attachments.central_item_id -> agenda_item_id
 * 3. central_subtasks.central_item_id -> agenda_item_id
 * 4. central_time_entries.central_item_id -> agenda_item_id
 * 5. central_item_watchers: drop coluna legacy central_item_id (tabela tem
 *    ambas apos migration 2026_03_12_230000)
 *
 * Todas as operacoes sao idempotentes via INFORMATION_SCHEMA / hasColumn:
 * em dev/teste (SQLite dump ja tem o schema correto), a migration vira
 * no-op. Em prod (MySQL com drifts), executa a correcao.
 *
 * Usa SQL raw (DB::statement) ao inves de Schema Builder para ter controle
 * total sobre ordem de operacoes e idempotencia em ambiente MySQL com
 * indices nomeados custom (ciw_item_user_unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ══════════════════════════════════════════════════════════════
        // 1. auto_assignment_rules: company_id -> tenant_id
        // ══════════════════════════════════════════════════════════════
        if (Schema::hasColumn('auto_assignment_rules', 'company_id')
            && ! Schema::hasColumn('auto_assignment_rules', 'tenant_id')) {
            DB::statement('ALTER TABLE auto_assignment_rules CHANGE company_id tenant_id BIGINT UNSIGNED NOT NULL');

            // Adicionar index em tenant_id se nao existir
            if (! $this->indexExists('auto_assignment_rules', 'auto_assignment_rules_tenant_id_index')) {
                DB::statement('CREATE INDEX auto_assignment_rules_tenant_id_index ON auto_assignment_rules (tenant_id)');
            }
        }

        // ══════════════════════════════════════════════════════════════
        // 2-4. central_attachments, central_subtasks, central_time_entries
        //      central_item_id -> agenda_item_id
        // ══════════════════════════════════════════════════════════════
        $this->renameCentralItemIdToAgendaItemId('central_attachments');
        $this->renameCentralItemIdToAgendaItemId('central_subtasks');
        $this->renameCentralItemIdToAgendaItemId('central_time_entries');

        // ══════════════════════════════════════════════════════════════
        // 5. central_item_watchers: consolidar em agenda_item_id
        // ══════════════════════════════════════════════════════════════
        if (Schema::hasColumn('central_item_watchers', 'central_item_id')
            && Schema::hasColumn('central_item_watchers', 'agenda_item_id')) {
            // 5a. Backfill agenda_item_id onde ainda estiver null
            DB::statement('
                UPDATE central_item_watchers
                SET agenda_item_id = central_item_id
                WHERE agenda_item_id IS NULL
            ');

            // 5b. Dropar FK central_item_watchers_central_item_id_foreign
            // (precisa ser antes do drop index UNIQUE, senao MySQL bloqueia)
            if ($this->foreignKeyExists('central_item_watchers', 'central_item_watchers_central_item_id_foreign')) {
                DB::statement('ALTER TABLE central_item_watchers DROP FOREIGN KEY central_item_watchers_central_item_id_foreign');
            }

            // 5c. Dropar UNIQUE ciw_item_user_unique (referencia central_item_id)
            if ($this->indexExists('central_item_watchers', 'ciw_item_user_unique')) {
                DB::statement('ALTER TABLE central_item_watchers DROP INDEX ciw_item_user_unique');
            }

            // 5d. Tornar agenda_item_id NOT NULL
            DB::statement('ALTER TABLE central_item_watchers MODIFY COLUMN agenda_item_id BIGINT UNSIGNED NOT NULL');

            // 5e. Dropar coluna central_item_id (MySQL dropa indices residuais automaticamente)
            DB::statement('ALTER TABLE central_item_watchers DROP COLUMN central_item_id');

            // 5f. Recriar UNIQUE ciw_item_user_unique sobre agenda_item_id
            if (! $this->indexExists('central_item_watchers', 'ciw_item_user_unique')) {
                DB::statement('CREATE UNIQUE INDEX ciw_item_user_unique ON central_item_watchers (agenda_item_id, user_id)');
            }

            // 5g. Criar FK agenda_item_id -> central_items.id
            // (preserva integridade referencial que a FK antiga garantia)
            if (! $this->foreignKeyExists('central_item_watchers', 'central_item_watchers_agenda_item_id_foreign')) {
                DB::statement('ALTER TABLE central_item_watchers ADD CONSTRAINT central_item_watchers_agenda_item_id_foreign FOREIGN KEY (agenda_item_id) REFERENCES central_items(id) ON DELETE CASCADE');
            }
        }
    }

    public function down(): void
    {
        // Reverter eh destrutivo e pode quebrar dados — no-op proposital.
        // Esta migration corrige drift historico e nao deve ser revertida.
    }

    /**
     * Helper idempotente: renomeia central_item_id -> agenda_item_id.
     *
     * Estrategia defensiva para compatibilidade com MySQL 5.7 e 8.x:
     * 1. Dropa FK sobre central_item_id (se existir)
     * 2. Executa ALTER TABLE CHANGE para renomear
     * 3. Recria FK com nome atualizado sobre agenda_item_id
     *
     * Em SQLite, so Schema::hasColumn vira false e a migration eh no-op.
     */
    private function renameCentralItemIdToAgendaItemId(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'central_item_id')
            || Schema::hasColumn($table, 'agenda_item_id')) {
            return;
        }

        $oldFk = "{$table}_central_item_id_foreign";
        $newFk = "{$table}_agenda_item_id_foreign";

        // 1. Dropar FK antiga se existir
        if ($this->foreignKeyExists($table, $oldFk)) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$oldFk}");
        }

        // 2. Renomear coluna
        DB::statement("ALTER TABLE {$table} CHANGE central_item_id agenda_item_id BIGINT UNSIGNED NOT NULL");

        // 3. Recriar FK sobre nova coluna (se nao existir)
        if (! $this->foreignKeyExists($table, $newFk)) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$newFk} FOREIGN KEY (agenda_item_id) REFERENCES central_items(id) ON DELETE CASCADE");
        }
    }

    /**
     * Verifica se uma FK existe via INFORMATION_SCHEMA (MySQL).
     */
    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql') {
            return false;
        }

        $database = $connection->getDatabaseName();
        $result = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $constraintName, 'FOREIGN KEY']
        );

        return ($result->cnt ?? 0) > 0;
    }

    /**
     * Verifica se um indice existe via INFORMATION_SCHEMA (MySQL).
     * Em SQLite retorna false sempre (driver nao tem INFORMATION_SCHEMA),
     * mas neste caso a migration ja foi no-op pelas checks de coluna anteriores.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql') {
            // Para SQLite/outros, usar Schema::getIndexListing se disponivel (Laravel 11+)
            try {
                $indexes = collect(Schema::getIndexes($table))->pluck('name')->all();

                return in_array($indexName, $indexes, true);
            } catch (Throwable) {
                return false;
            }
        }

        $database = $connection->getDatabaseName();
        $result = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$database, $table, $indexName]
        );

        return ($result->cnt ?? 0) > 0;
    }
};
