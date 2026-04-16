<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // work_order_time_logs — zero indexes, used in time tracking reports
        if (Schema::hasTable('work_order_time_logs')) {
            Schema::table('work_order_time_logs', function (Blueprint $table) {
                $table->index(['tenant_id', 'work_order_id', 'started_at'], 'wotl_tenant_wo_started');
                $table->index(['tenant_id', 'user_id', 'started_at'], 'wotl_tenant_user_started');
            });
        }

        // service_calls — only has [tenant_id, status]
        if (Schema::hasTable('service_calls')) {
            Schema::table('service_calls', function (Blueprint $table) {
                $table->index(['tenant_id', 'technician_id', 'status'], 'sc_tenant_tech_status');
                $table->index(['tenant_id', 'customer_id'], 'sc_tenant_customer');
            });
        }

        // work_order_status_history — no index on work_order_id for timeline
        if (Schema::hasTable('work_order_status_history')) {
            Schema::table('work_order_status_history', function (Blueprint $table) {
                $table->index(['work_order_id', 'created_at'], 'wosh_wo_created');
            });
        }

        // sla_policies — no tenant_id index
        if (Schema::hasTable('sla_policies')) {
            Schema::table('sla_policies', function (Blueprint $table) {
                $table->index(['tenant_id', 'is_active'], 'sla_tenant_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_order_time_logs')) {
            Schema::table('work_order_time_logs', function (Blueprint $table) {
                $table->dropIndex('wotl_tenant_wo_started');
                $table->dropIndex('wotl_tenant_user_started');
            });
        }

        if (Schema::hasTable('service_calls')) {
            Schema::table('service_calls', function (Blueprint $table) {
                $table->dropIndex('sc_tenant_tech_status');
                $table->dropIndex('sc_tenant_customer');
            });
        }

        if (Schema::hasTable('work_order_status_history')) {
            Schema::table('work_order_status_history', function (Blueprint $table) {
                $table->dropIndex('wosh_wo_created');
            });
        }

        if (Schema::hasTable('sla_policies')) {
            Schema::table('sla_policies', function (Blueprint $table) {
                $table->dropIndex('sla_tenant_active');
            });
        }
    }
};
