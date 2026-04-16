<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeCommissionRoleColumns();
        $this->dropDuplicateCommissionGoalsUniqueIndex();
    }

    public function down(): void
    {
        $this->restoreLegacyCommissionRoleDefaults();
        $this->restoreDuplicateCommissionGoalsUniqueIndex();
    }

    private function normalizeCommissionRoleColumns(): void
    {
        $roleMappings = [
            'technician' => 'tecnico',
            'seller' => 'vendedor',
            'salesperson' => 'vendedor',
            'driver' => 'motorista',
        ];

        foreach ([
            'commission_rules' => 'applies_to_role',
            'commission_campaigns' => 'applies_to_role',
            'commission_splits' => 'role',
        ] as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach ($roleMappings as $legacy => $canonical) {
                DB::table($table)->where($column, $legacy)->update([$column => $canonical]);
            }
        }

        if (! Schema::hasTable('commission_rules') || ! Schema::hasColumn('commission_rules', 'applies_to_role')) {
            return;
        }

        if ($this->driver() === 'mysql') {
            DB::statement("ALTER TABLE commission_rules MODIFY applies_to_role VARCHAR(20) NOT NULL DEFAULT 'tecnico'");
        }

        if (Schema::hasTable('commission_splits') && Schema::hasColumn('commission_splits', 'role') && $this->driver() === 'mysql') {
            DB::statement("ALTER TABLE commission_splits MODIFY role VARCHAR(20) NOT NULL DEFAULT 'tecnico'");
        }
    }

    private function restoreLegacyCommissionRoleDefaults(): void
    {
        $roleMappings = [
            'tecnico' => 'technician',
            'vendedor' => 'seller',
            'motorista' => 'driver',
        ];

        foreach ([
            'commission_rules' => 'applies_to_role',
            'commission_campaigns' => 'applies_to_role',
            'commission_splits' => 'role',
        ] as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach ($roleMappings as $canonical => $legacy) {
                DB::table($table)->where($column, $canonical)->update([$column => $legacy]);
            }
        }

        if ($this->driver() === 'mysql' && Schema::hasTable('commission_rules') && Schema::hasColumn('commission_rules', 'applies_to_role')) {
            DB::statement("ALTER TABLE commission_rules MODIFY applies_to_role VARCHAR(20) NOT NULL DEFAULT 'technician'");
        }

        if ($this->driver() === 'mysql' && Schema::hasTable('commission_splits') && Schema::hasColumn('commission_splits', 'role')) {
            DB::statement("ALTER TABLE commission_splits MODIFY role VARCHAR(20) NOT NULL DEFAULT 'technician'");
        }
    }

    private function dropDuplicateCommissionGoalsUniqueIndex(): void
    {
        if (! Schema::hasTable('commission_goals')) {
            return;
        }

        $legacyDuplicate = 'commission_goals_tenant_id_user_id_period_type_unique';
        if ($this->indexExists('commission_goals', $legacyDuplicate)) {
            Schema::table('commission_goals', function (Blueprint $table) use ($legacyDuplicate) {
                $table->dropUnique($legacyDuplicate);
            });
        }
    }

    private function restoreDuplicateCommissionGoalsUniqueIndex(): void
    {
        if (! Schema::hasTable('commission_goals')) {
            return;
        }

        $legacyDuplicate = 'commission_goals_tenant_id_user_id_period_type_unique';
        if ($this->indexExists('commission_goals', $legacyDuplicate)) {
            return;
        }

        Schema::table('commission_goals', function (Blueprint $table) use ($legacyDuplicate) {
            $table->unique(['tenant_id', 'user_id', 'period', 'type'], $legacyDuplicate);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return match ($this->driver()) {
            'mysql' => collect(DB::select('SHOW INDEX FROM `'.$table.'`'))
                ->contains(fn (object $index) => ($index->Key_name ?? null) === $indexName),
            'sqlite' => collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn (object $index) => ($index->name ?? null) === $indexName),
            default => false,
        };
    }

    private function driver(): string
    {
        return Schema::getConnection()->getDriverName();
    }
};
