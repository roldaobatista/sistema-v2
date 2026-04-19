<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * data-03 (S1): tenants.slug e tenants.document nao tinham UNIQUE.
 * Permitia dois tenants com mesmo CNPJ/slug — vazamento de identidade legal
 * e colisao em qualquer lookup deterministico por slug.
 *
 * Esta migration e idempotente (H3 guards) e tolerante a dados existentes
 * duplicados: se houver colisao em document, renomeia copias com sufixo
 * "_dup_<id>" para permitir o UNIQUE sem perder dados — a deduplicacao
 * real fica a cargo de rotina operacional subsequente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        $this->dedupeColumn('tenants', 'document');
        $this->dedupeColumn('tenants', 'slug');

        Schema::table('tenants', function (Blueprint $t) {
            if (! $this->hasIndex('tenants', 'tenants_document_unique')) {
                $t->unique('document', 'tenants_document_unique');
            }
            if (! $this->hasIndex('tenants', 'tenants_slug_unique')) {
                $t->unique('slug', 'tenants_slug_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $t) {
            if ($this->hasIndex('tenants', 'tenants_document_unique')) {
                $t->dropUnique('tenants_document_unique');
            }
            if ($this->hasIndex('tenants', 'tenants_slug_unique')) {
                $t->dropUnique('tenants_slug_unique');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?",
                [$table, $indexName]
            );

            return $row !== null;
        }

        $schema = $connection->getDatabaseName();
        $row = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$schema, $table, $indexName]
        );

        return $row !== null;
    }

    private function dedupeColumn(string $table, string $column): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        $duplicates = DB::select(
            "SELECT `$column` AS value, COUNT(*) AS total
             FROM `$table`
             WHERE `$column` IS NOT NULL AND `$column` <> ''
             GROUP BY `$column`
             HAVING total > 1"
        );

        foreach ($duplicates as $dup) {
            $rows = DB::select(
                "SELECT id FROM `$table` WHERE `$column` = ? ORDER BY id ASC",
                [$dup->value]
            );

            foreach (array_slice($rows, 1) as $row) {
                DB::update(
                    "UPDATE `$table` SET `$column` = CONCAT(?, '_dup_', id) WHERE id = ?",
                    [$dup->value, $row->id]
                );
            }
        }
    }
};
