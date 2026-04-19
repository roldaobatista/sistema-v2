<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair migration para drift causado por 2026_04_17_220000_normalize_monetary_precision.
 *
 * A migration original usou `->decimal(15,2)->change()` em `amount_paid`
 * (accounts_payable e accounts_receivable). O `->change()` via doctrine/dbal
 * recria a definicao da coluna mas NAO preserva o default original
 * (`->default(0)` da migration 2026_02_07_600000_create_financial_tables).
 *
 * Resultado em MySQL/MariaDB: coluna fica `NOT NULL sem default`, quebrando
 * INSERTs que dependiam do default implicito '0.00'.
 *
 * Em SQLite o `->change()` e no-op efetivo, entao o default original foi
 * preservado no schema dump antigo gerado via artisan schema:dump.
 * Isso mascarou o drift ate a regeneracao do dump via generate_sqlite_schema.php
 * (conversao MySQL->SQLite) expor a incoerencia.
 *
 * Esta migration restaura o default `0.00` nas duas colunas afetadas.
 * Idempotente via check de COLUMN_DEFAULT no information_schema.
 */
return new class extends Migration
{
    private array $targets = [
        ['table' => 'accounts_payable', 'column' => 'amount_paid', 'default' => '0.00'],
        ['table' => 'accounts_receivable', 'column' => 'amount_paid', 'default' => '0.00'],
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->targets as $t) {
            if (! Schema::hasTable($t['table']) || ! Schema::hasColumn($t['table'], $t['column'])) {
                continue;
            }

            if ($this->hasDefault($t['table'], $t['column'])) {
                continue;
            }

            DB::statement(sprintf(
                "ALTER TABLE `%s` MODIFY `%s` DECIMAL(15,2) NOT NULL DEFAULT %s",
                $t['table'],
                $t['column'],
                DB::getPdo()->quote($t['default'])
            ));
        }
    }

    public function down(): void
    {
        // No-op: restaurar default nao tem contraparte destrutiva.
    }

    private function hasDefault(string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT COLUMN_DEFAULT FROM information_schema.columns
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $row !== null && $row->COLUMN_DEFAULT !== null;
    }
};
