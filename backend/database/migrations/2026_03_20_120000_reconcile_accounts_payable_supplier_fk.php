<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function getForeignKeyNames(string $table, string $column): array
    {
        if (DB::getDriverName() !== 'mysql') {
            return [];
        }

        $database = DB::getDatabaseName();

        /** @var list<object{CONSTRAINT_NAME:string}> $rows */
        $rows = DB::select(
            <<<'SQL'
                SELECT DISTINCT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            SQL,
            [$database, $table, $column],
        );

        return array_values(array_unique(array_map(
            static fn (object $row): string => (string) $row->CONSTRAINT_NAME,
            $rows,
        )));
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $possibleNames = array_values(array_unique(array_filter([
            $table.'_'.$column.'_foreign',
            ...$this->getForeignKeyNames($table, $column),
        ])));

        if (DB::getDriverName() === 'mysql') {
            foreach ($possibleNames as $name) {
                try {
                    DB::statement(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $name));
                } catch (Throwable) {
                }
            }

            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $possibleNames) {
            foreach ($possibleNames as $name) {
                try {
                    $tableBlueprint->dropForeign($name);
                } catch (Throwable) {
                }
            }

            try {
                $tableBlueprint->dropForeign([$column]);
            } catch (Throwable) {
            }
        });
    }

    public function up(): void
    {
        if (! Schema::hasTable('accounts_payable') || ! Schema::hasTable('suppliers')) {
            return;
        }

        if (! Schema::hasColumn('accounts_payable', 'supplier_id')) {
            Schema::table('accounts_payable', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            });

            return;
        }

        $this->dropForeignKeyIfExists('accounts_payable', 'supplier_id');

        Schema::table('accounts_payable', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Intencionalmente no-op.
        // Esta migration só reconcilia o schema legado sem reintroduzir o conflito
        // histórico de ownership entre migrations antigas durante rollback.
    }
};
