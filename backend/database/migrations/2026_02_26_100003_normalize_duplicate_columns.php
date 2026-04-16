<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normaliza colunas adicionadas em mais de uma migration (quotes.source, customers.segment).
 * Garante tipo consistente. Usa SQL bruto para não depender de doctrine/dbal.
 * MODIFY é MySQL; em SQLite não alteramos tipo (coluna já existe).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('quotes', 'source')) {
            DB::statement('ALTER TABLE quotes MODIFY source VARCHAR(50) NULL');
        }

        if (Schema::hasColumn('customers', 'segment')) {
            $maxLen = DB::selectOne('SELECT MAX(LENGTH(segment)) as m FROM customers WHERE segment IS NOT NULL');
            $maxLen = (int) ($maxLen->m ?? 0);
            if ($maxLen <= 50) {
                DB::statement('ALTER TABLE customers MODIFY segment VARCHAR(50) NULL');
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('quotes', 'source')) {
            DB::statement('ALTER TABLE quotes MODIFY source VARCHAR(50) NULL');
        }
        if (Schema::hasColumn('customers', 'segment')) {
            DB::statement('ALTER TABLE customers MODIFY segment VARCHAR(255) NULL');
        }
    }
};
