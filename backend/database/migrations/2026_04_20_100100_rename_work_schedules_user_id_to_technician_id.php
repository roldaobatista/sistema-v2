<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('work_schedules')
            || Schema::hasColumn('work_schedules', 'technician_id')
            || ! Schema::hasColumn('work_schedules', 'user_id')
        ) {
            return;
        }

        if ($this->hasIndex('work_schedules', 'work_schedules_user_id_date_unique')) {
            Schema::table('work_schedules', function (Blueprint $table): void {
                $table->dropUnique('work_schedules_user_id_date_unique');
            });
        }

        $this->dropForeignIfExists('work_schedules', 'work_schedules_user_id_foreign');

        Schema::table('work_schedules', function (Blueprint $table): void {
            $table->renameColumn('user_id', 'technician_id');
        });

        Schema::table('work_schedules', function (Blueprint $table): void {
            if (! $this->hasForeign('work_schedules', 'work_schedules_technician_id_foreign')) {
                $table->foreign('technician_id', 'work_schedules_technician_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            }

            if (! $this->hasIndex('work_schedules', 'work_schedules_tenant_technician_date_unique')) {
                $table->unique(['tenant_id', 'technician_id', 'date'], 'work_schedules_tenant_technician_date_unique');
            }
        });
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('work_schedules')
            || ! Schema::hasColumn('work_schedules', 'technician_id')
            || Schema::hasColumn('work_schedules', 'user_id')
        ) {
            return;
        }

        if ($this->hasIndex('work_schedules', 'work_schedules_tenant_technician_date_unique')) {
            Schema::table('work_schedules', function (Blueprint $table): void {
                $table->dropUnique('work_schedules_tenant_technician_date_unique');
            });
        }

        $this->dropForeignIfExists('work_schedules', 'work_schedules_technician_id_foreign');

        Schema::table('work_schedules', function (Blueprint $table): void {
            $table->renameColumn('technician_id', 'user_id');
        });

        Schema::table('work_schedules', function (Blueprint $table): void {
            if (! $this->hasForeign('work_schedules', 'work_schedules_user_id_foreign')) {
                $table->foreign('user_id', 'work_schedules_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            }

            if (! $this->hasIndex('work_schedules', 'work_schedules_user_id_date_unique')) {
                $table->unique(['user_id', 'date'], 'work_schedules_user_id_date_unique');
            }
        });
    }

    private function dropForeignIfExists(string $table, string $foreignName): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite' || ! $this->hasForeign($table, $foreignName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($foreignName): void {
            $table->dropForeign($foreignName);
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

    private function hasForeign(string $table, string $foreignName): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return false;
        }

        $schema = $connection->getDatabaseName();
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.table_constraints
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = ? LIMIT 1',
            [$schema, $table, $foreignName, 'FOREIGN KEY']
        );

        return $row !== null;
    }
};
