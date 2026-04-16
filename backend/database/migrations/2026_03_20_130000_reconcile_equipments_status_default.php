<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('equipments') || ! Schema::hasColumn('equipments', 'status')) {
            return;
        }

        DB::table('equipments')->where('status', 'ativo')->update(['status' => 'active']);

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $default = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'equipments')
            ->where('COLUMN_NAME', 'status')
            ->value('COLUMN_DEFAULT');

        if ($default !== 'active') {
            DB::statement("ALTER TABLE `equipments` MODIFY `status` VARCHAR(30) NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        // Forward-only corrective migration:
        // the application contract already uses english statuses, so reverting
        // persisted data back to legacy portuguese values would reintroduce drift.
    }
};
