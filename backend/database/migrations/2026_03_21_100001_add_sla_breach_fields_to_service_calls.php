<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_calls', function (Blueprint $table) {
            if (! Schema::hasColumn('service_calls', 'sla_response_breached')) {
                $table->boolean('sla_response_breached')->default(false)->after('sla_due_at');
            }
            if (! Schema::hasColumn('service_calls', 'sla_resolution_breached')) {
                $table->boolean('sla_resolution_breached')->default(false)->after('sla_response_breached');
            }
            if (! Schema::hasColumn('service_calls', 'sla_policy_id')) {
                $table->unsignedBigInteger('sla_policy_id')->nullable()->after('sla_resolution_breached');
                $table->foreign('sla_policy_id')->references('id')->on('sla_policies')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_calls', function (Blueprint $table) {
            $table->dropForeign(['sla_policy_id']);
            $table->dropColumn(['sla_response_breached', 'sla_resolution_breached', 'sla_policy_id']);
        });
    }
};
