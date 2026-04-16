<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_activities') && ! Schema::hasColumn('crm_activities', 'contact_id')) {
            Schema::table('crm_activities', function (Blueprint $table) {
                $table->foreignId('contact_id')->nullable()
                    ->constrained('customer_contacts')->nullOnDelete();
                $table->index(['tenant_id', 'contact_id'], 'crm_act_tenant_contact_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_activities') && Schema::hasColumn('crm_activities', 'contact_id')) {
            Schema::table('crm_activities', function (Blueprint $table) {
                $table->dropForeign(['contact_id']);
                $table->dropIndex('crm_act_tenant_contact_idx');
                $table->dropColumn('contact_id');
            });
        }
    }
};
