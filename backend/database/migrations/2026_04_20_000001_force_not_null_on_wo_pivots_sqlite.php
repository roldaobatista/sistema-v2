<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * data-03 (Re-auditoria Camada 1 r4).
 *
 * Forca `tenant_id NOT NULL` em `work_order_equipments` e
 * `work_order_technicians` no SQLite — contrato que deveria ter sido
 * estabelecido pela 2026_04_19_500003 (data-04), mas que o Doctrine DBAL
 * nao consegue aplicar via `->change()` em SQLite (SQLite nao suporta
 * ALTER COLUMN; o shim do DBAL nao recriou as tabelas corretamente
 * porque ja existia coluna com default NULL). Resultado: MySQL esta OK
 * apos 500003, mas o dump `sqlite-schema.sql` mantem
 * `tenant_id integer DEFAULT NULL` — testes SQLite aceitam NULL.
 *
 * Estrategia (SQLite-only — em MySQL vira noop):
 *  1. Backfill qualquer remanescente de `tenant_id IS NULL` via JOIN na
 *     `work_orders` pai (idempotente — se tabela ja limpa, WHERE zera).
 *  2. Abortar se sobrar NULL (nao deletamos dados automaticamente).
 *  3. Recriar tabela com `tenant_id INTEGER NOT NULL` via table-copy
 *     pattern do SQLite:
 *        CREATE new; INSERT SELECT; DROP old; RENAME new.
 *  4. Recriar indices que existiam na tabela original.
 *
 * Em MySQL: noop — `columnIsNullable()` ja retorna `false` depois da
 * 500003; skip imediato.
 *
 * Rollback (`down`): reverte para `tenant_id` nullable via mesma
 * tecnica de table-copy (mantem dados).
 */
return new class extends Migration
{
    /**
     * Map: tabela -> [colunas em ordem da CREATE TABLE, indices a recriar].
     *
     * Fonte: `database/schema/sqlite-schema.sql` linhas 10400-10412 e 10539-10551.
     *
     * @var array<string, array{columns: string, indexes: array<int, string>, unique_indexes?: array<int, string>}>
     */
    private array $map = [
        'work_order_equipments' => [
            'columns' => '
                "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                "work_order_id" integer NOT NULL,
                "equipment_id" integer NOT NULL,
                "observations" text,
                "created_at" datetime NULL DEFAULT NULL,
                "updated_at" datetime NULL DEFAULT NULL,
                "tenant_id" integer NOT NULL
            ',
            'indexes' => [
                'CREATE INDEX "work_order_equipments_work_order_equipment_tenant_idx" ON "work_order_equipments" ("tenant_id")',
                'CREATE INDEX "work_order_equipments_work_order_id_fk_idx" ON "work_order_equipments" ("work_order_id")',
                'CREATE INDEX "work_order_equipments_equipment_id_fk_idx" ON "work_order_equipments" ("equipment_id")',
                'CREATE INDEX "work_order_equipments_tenant_id_idx" ON "work_order_equipments" ("tenant_id")',
            ],
        ],
        'work_order_technicians' => [
            'columns' => '
                "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                "work_order_id" integer NOT NULL,
                "user_id" integer NOT NULL,
                "role" varchar(20) NOT NULL DEFAULT \'tecnico\',
                "created_at" datetime NULL DEFAULT NULL,
                "updated_at" datetime NULL DEFAULT NULL,
                "tenant_id" integer NOT NULL
            ',
            'indexes' => [
                'CREATE INDEX "work_order_technicians_user_id_foreign" ON "work_order_technicians" ("user_id")',
                'CREATE INDEX "work_order_technicians_work_order_technicia_tenant_idx" ON "work_order_technicians" ("tenant_id")',
                'CREATE INDEX "work_order_technicians_tenant_id_idx" ON "work_order_technicians" ("tenant_id")',
            ],
            'unique_indexes' => [
                'CREATE UNIQUE INDEX "work_order_technicians_work_order_id_user_id_unique" ON "work_order_technicians" ("work_order_id","user_id")',
            ],
        ],
    ];

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // MySQL: a 500003 ja forcou NOT NULL via Doctrine DBAL (funciona no MySQL).
        // Detectamos e pulamos — evita downtime de recreate em producao.
        if ($driver !== 'sqlite') {
            return;
        }

        foreach ($this->map as $table => $spec) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            if (! $this->columnIsNullable($table, 'tenant_id')) {
                continue; // ja NOT NULL — idempotente
            }

            // 1. Backfill idempotente (se 500003 ja rodou, WHERE IS NULL retorna 0).
            DB::statement(
                "UPDATE {$table} SET tenant_id = (SELECT p.tenant_id FROM work_orders p WHERE p.id = {$table}.work_order_id) WHERE tenant_id IS NULL"
            );

            $orphans = DB::table($table)->whereNull('tenant_id')->count();
            if ($orphans > 0) {
                throw new RuntimeException(sprintf(
                    '[data-03] %s tem %d linhas com tenant_id NULL apos backfill via work_orders. Limpeza manual obrigatoria.',
                    $table,
                    $orphans,
                ));
            }

            // 2. Table-copy pattern (SQLite nao suporta ALTER COLUMN SET NOT NULL).
            $this->recreateTable($table, $spec['columns'], $spec['indexes'], $spec['unique_indexes'] ?? []);
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'sqlite') {
            return;
        }

        // Rollback: recreate com tenant_id NULL (troca NOT NULL por DEFAULT NULL).
        $reverseMap = [
            'work_order_equipments' => [
                'columns' => '
                    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                    "work_order_id" integer NOT NULL,
                    "equipment_id" integer NOT NULL,
                    "observations" text,
                    "created_at" datetime NULL DEFAULT NULL,
                    "updated_at" datetime NULL DEFAULT NULL,
                    "tenant_id" integer DEFAULT NULL
                ',
                'indexes' => $this->map['work_order_equipments']['indexes'],
            ],
            'work_order_technicians' => [
                'columns' => '
                    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                    "work_order_id" integer NOT NULL,
                    "user_id" integer NOT NULL,
                    "role" varchar(20) NOT NULL DEFAULT \'tecnico\',
                    "created_at" datetime NULL DEFAULT NULL,
                    "updated_at" datetime NULL DEFAULT NULL,
                    "tenant_id" integer DEFAULT NULL
                ',
                'indexes' => $this->map['work_order_technicians']['indexes'],
                'unique_indexes' => $this->map['work_order_technicians']['unique_indexes'] ?? [],
            ],
        ];

        foreach ($reverseMap as $table => $spec) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $this->recreateTable($table, $spec['columns'], $spec['indexes'], $spec['unique_indexes'] ?? []);
        }
    }

    /**
     * @param  array<int, string>  $indexes
     * @param  array<int, string>  $uniqueIndexes
     */
    private function recreateTable(string $table, string $columns, array $indexes, array $uniqueIndexes = []): void
    {
        $tmp = "{$table}__new";

        // Lista de colunas preservadas no INSERT SELECT. Extrai nomes do DDL.
        preg_match_all('/"([a-z_]+)"\s+\w+/i', $columns, $m);
        $columnList = implode(', ', array_map(fn ($c) => "\"{$c}\"", $m[1]));

        DB::statement("CREATE TABLE \"{$tmp}\" ({$columns})");
        DB::statement("INSERT INTO \"{$tmp}\" ({$columnList}) SELECT {$columnList} FROM \"{$table}\"");
        DB::statement("DROP TABLE \"{$table}\"");
        DB::statement("ALTER TABLE \"{$tmp}\" RENAME TO \"{$table}\"");

        foreach ($uniqueIndexes as $idx) {
            DB::statement($idx);
        }
        foreach ($indexes as $idx) {
            DB::statement($idx);
        }
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        $info = DB::select("PRAGMA table_info('{$table}')");
        foreach ($info as $col) {
            if ($col->name === $column) {
                return (int) $col->notnull === 0;
            }
        }

        return true;
    }
};
