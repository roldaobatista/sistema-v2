<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'work_order_events',
            'work_order_ratings',
            'work_order_displacement_locations',
            'work_order_displacement_stops',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('tenant_id')->nullable()->after('id');
                    $t->index('tenant_id');
                });

                $driver = Schema::getConnection()->getDriverName();

                if ($driver === 'sqlite') {
                    DB::statement("
                        UPDATE {$table}
                        SET tenant_id = (
                            SELECT wo.tenant_id FROM work_orders wo
                            WHERE wo.id = {$table}.work_order_id
                        )
                        WHERE tenant_id IS NULL
                    ");
                } else {
                    DB::statement("
                        UPDATE {$table} t
                        INNER JOIN work_orders wo ON wo.id = t.work_order_id
                        SET t.tenant_id = wo.tenant_id
                        WHERE t.tenant_id IS NULL
                    ");
                }

                if ($driver !== 'sqlite') {
                    Schema::table($table, function (Blueprint $t) {
                        $t->unsignedBigInteger('tenant_id')->nullable(false)->change();
                    });
                }
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'work_order_events',
            'work_order_ratings',
            'work_order_displacement_locations',
            'work_order_displacement_stops',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['tenant_id']);
                    $t->dropColumn('tenant_id');
                });
            }
        }
    }
};
