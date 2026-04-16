<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_order_items') && ! Schema::hasColumn('work_order_items', 'warehouse_id')) {
            Schema::table('work_order_items', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('total')
                    ->constrained('warehouses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_order_items') && Schema::hasColumn('work_order_items', 'warehouse_id')) {
            Schema::table('work_order_items', function (Blueprint $table) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            });
        }
    }
};
