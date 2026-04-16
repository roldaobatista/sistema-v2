<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fiscal_invoices')) {
            Schema::table('fiscal_invoices', function (Blueprint $table) {
                $table->unique(['tenant_id', 'number'], 'fiscal_invoices_tenant_number_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fiscal_invoices')) {
            Schema::table('fiscal_invoices', function (Blueprint $table) {
                $table->dropUnique('fiscal_invoices_tenant_number_unique');
            });
        }
    }
};
