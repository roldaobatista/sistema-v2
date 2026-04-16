<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_quotation_items') || Schema::hasColumn('purchase_quotation_items', 'tenant_id')) {
            return;
        }

        Schema::table('purchase_quotation_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
        });

        Schema::table('purchase_quotation_items', function (Blueprint $table) {
            $table->index(['tenant_id', 'purchase_quotation_id'], 'pqi_tenant_quotation_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_quotation_items') || ! Schema::hasColumn('purchase_quotation_items', 'tenant_id')) {
            return;
        }

        Schema::table('purchase_quotation_items', function (Blueprint $table) {
            $table->dropIndex('pqi_tenant_quotation_idx');
            $table->dropColumn('tenant_id');
        });
    }
};
