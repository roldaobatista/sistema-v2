<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_deal_competitors', 'tenant_id')) {
            Schema::table('crm_deal_competitors', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->after('id')->default(0);
                $table->index('tenant_id');
            });

            DB::statement('
                UPDATE crm_deal_competitors
                SET tenant_id = (SELECT tenant_id FROM crm_deals WHERE crm_deals.id = crm_deal_competitors.deal_id)
                WHERE EXISTS (SELECT 1 FROM crm_deals WHERE crm_deals.id = crm_deal_competitors.deal_id)
            ');
        }

        if (! Schema::hasColumn('crm_sequence_steps', 'tenant_id')) {
            Schema::table('crm_sequence_steps', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->after('id')->default(0);
                $table->index('tenant_id');
            });

            DB::statement('
                UPDATE crm_sequence_steps
                SET tenant_id = (SELECT tenant_id FROM crm_sequences WHERE crm_sequences.id = crm_sequence_steps.sequence_id)
                WHERE EXISTS (SELECT 1 FROM crm_sequences WHERE crm_sequences.id = crm_sequence_steps.sequence_id)
            ');
        }

        if (! Schema::hasColumn('crm_territory_members', 'tenant_id')) {
            Schema::table('crm_territory_members', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->after('id')->default(0);
                $table->index('tenant_id');
            });

            DB::statement('
                UPDATE crm_territory_members
                SET tenant_id = (SELECT tenant_id FROM crm_territories WHERE crm_territories.id = crm_territory_members.territory_id)
                WHERE EXISTS (SELECT 1 FROM crm_territories WHERE crm_territories.id = crm_territory_members.territory_id)
            ');
        }
    }

    public function down(): void
    {
        foreach (['crm_deal_competitors', 'crm_sequence_steps', 'crm_territory_members'] as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropIndex(['tenant_id']);
                    $blueprint->dropColumn('tenant_id');
                });
            }
        }
    }
};
