<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $indexes = $this->getIndexNames('work_orders');

            if (! in_array('work_orders_tenant_id_status_index', $indexes)) {
                $table->index(['tenant_id', 'status'], 'work_orders_tenant_id_status_index');
            }
            if (! in_array('work_orders_tenant_id_customer_id_index', $indexes)) {
                $table->index(['tenant_id', 'customer_id'], 'work_orders_tenant_id_customer_id_index');
            }
            if (! in_array('work_orders_tenant_id_assigned_to_status_index', $indexes)) {
                $table->index(['tenant_id', 'assigned_to', 'status'], 'work_orders_tenant_id_assigned_to_status_index');
            }
        });

        Schema::table('work_order_items', function (Blueprint $table) {
            $indexes = $this->getIndexNames('work_order_items');

            if (! in_array('work_order_items_work_order_id_type_index', $indexes)) {
                $table->index(['work_order_id', 'type'], 'work_order_items_work_order_id_type_index');
            }
        });

        Schema::table('work_order_status_history', function (Blueprint $table) {
            $indexes = $this->getIndexNames('work_order_status_history');

            if (! in_array('wo_status_history_wo_id_created_at_index', $indexes)) {
                $table->index(['work_order_id', 'created_at'], 'wo_status_history_wo_id_created_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex('work_orders_tenant_id_status_index');
            $table->dropIndex('work_orders_tenant_id_customer_id_index');
            $table->dropIndex('work_orders_tenant_id_assigned_to_status_index');
        });

        Schema::table('work_order_items', function (Blueprint $table) {
            $table->dropIndex('work_order_items_work_order_id_type_index');
        });

        Schema::table('work_order_status_history', function (Blueprint $table) {
            $table->dropIndex('wo_status_history_wo_id_created_at_index');
        });
    }

    private function getIndexNames(string $table): array
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list(\"{$table}\")"))
                ->pluck('name')->toArray();
        }

        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')->unique()->toArray();
    }
};
