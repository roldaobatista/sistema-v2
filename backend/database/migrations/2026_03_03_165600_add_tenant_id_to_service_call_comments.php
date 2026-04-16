<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_call_comments', function (Blueprint $t) {
            if (! Schema::hasColumn('service_call_comments', 'tenant_id')) {
                $t->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }
        });

        // Backfill existing comments with tenant_id from their parent service_call
        DB::statement('
            UPDATE service_call_comments
            SET tenant_id = (
                SELECT tenant_id FROM service_calls
                WHERE service_calls.id = service_call_comments.service_call_id
            )
            WHERE tenant_id IS NULL
        ');

        Schema::table('service_call_comments', function (Blueprint $t) {
            if (Schema::hasColumn('service_call_comments', 'tenant_id')) {
                // Make non-nullable after backfill
                $t->unsignedBigInteger('tenant_id')->nullable(false)->change();
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->index(['tenant_id', 'service_call_id'], 'scc_tenant_sc_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_call_comments', function (Blueprint $t) {
            if (Schema::hasColumn('service_call_comments', 'tenant_id')) {
                try {
                    $t->dropForeign(['tenant_id']);
                } catch (Throwable) {
                }

                try {
                    $t->dropIndex('scc_tenant_sc_idx');
                } catch (Throwable) {
                }
                $t->dropColumn('tenant_id');
            }
        });
    }
};
