<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona tenant_id à tabela inventory_items para isolamento por tenant.
 * Preenche a partir de inventories. Coluna nullable para registros órfãos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_items') || Schema::hasColumn('inventory_items', 'tenant_id')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
        });

        if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'tenant_id')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::statement('
                    UPDATE inventory_items
                    SET tenant_id = (SELECT tenant_id FROM inventories WHERE inventories.id = inventory_items.inventory_id)
                    WHERE tenant_id IS NULL
                ');
            } else {
                DB::statement('
                    UPDATE inventory_items ii
                    INNER JOIN inventories i ON ii.inventory_id = i.id
                    SET ii.tenant_id = i.tenant_id
                    WHERE ii.tenant_id IS NULL
                ');
            }
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->index('tenant_id', 'inv_items_tenant_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_items') || ! Schema::hasColumn('inventory_items', 'tenant_id')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex('inv_items_tenant_idx');
            $table->dropColumn('tenant_id');
        });
    }
};
