<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // customers — CRM scopes: seller, segment, health, follow-up, contact
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index(['tenant_id', 'is_active'], 'cust_tenant_active');
                $table->index(['tenant_id', 'assigned_seller_id'], 'cust_tenant_seller');
                $table->index(['tenant_id', 'last_contact_at'], 'cust_tenant_contact');
                $table->index(['tenant_id', 'next_follow_up_at'], 'cust_tenant_followup');
                $table->index(['tenant_id', 'health_score'], 'cust_tenant_health');
            });
        }

        // work_orders — assigned_to, priority, branch_id
        if (Schema::hasTable('work_orders')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->index(['tenant_id', 'assigned_to'], 'wo_tenant_assigned');
                $table->index(['tenant_id', 'priority'], 'wo_tenant_priority');
                $table->index('branch_id', 'wo_branch');
                $table->index('sla_policy_id', 'wo_sla_policy');
            });
        }

        // equipments — calibration scopes, status, is_active
        if (Schema::hasTable('equipments')) {
            Schema::table('equipments', function (Blueprint $table) {
                $table->index(['tenant_id', 'is_active'], 'eq_tenant_active');
                $table->index(['tenant_id', 'next_calibration_at'], 'eq_tenant_calib');
                $table->index(['tenant_id', 'status'], 'eq_tenant_status');
            });
        }

        // accounts_receivable — work_order_id, paid_at
        if (Schema::hasTable('accounts_receivable')) {
            Schema::table('accounts_receivable', function (Blueprint $table) {
                $table->index('work_order_id', 'ar_work_order');
                $table->index(['tenant_id', 'paid_at'], 'ar_tenant_paid');
            });
        }

        // accounts_payable — chart_of_account_id, supplier_id
        if (Schema::hasTable('accounts_payable')) {
            Schema::table('accounts_payable', function (Blueprint $table) {
                $table->index('chart_of_account_id', 'ap_chart_account');
            });
        }

        // expenses — work_order_id, created_by, category
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->index('work_order_id', 'exp_work_order');
                $table->index('created_by', 'exp_created_by');
                $table->index('expense_category_id', 'exp_category');
            });
        }

        // schedules — status, customer_id, work_order_id
        if (Schema::hasTable('schedules')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'sched_tenant_status');
                $table->index(['tenant_id', 'customer_id'], 'sched_tenant_customer');
                $table->index('work_order_id', 'sched_work_order');
            });
        }

        // time_entries — work_order_id
        if (Schema::hasTable('time_entries')) {
            Schema::table('time_entries', function (Blueprint $table) {
                $table->index('work_order_id', 'te_work_order');
            });
        }

        // commission_events — work_order_id
        if (Schema::hasTable('commission_events')) {
            Schema::table('commission_events', function (Blueprint $table) {
                $table->index('work_order_id', 'ce_work_order');
                $table->index(['tenant_id', 'created_at'], 'ce_tenant_created');
            });
        }

        // commission_settlements — status, paid_at
        if (Schema::hasTable('commission_settlements')) {
            Schema::table('commission_settlements', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'cs_tenant_status');
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'customers' => ['cust_tenant_active', 'cust_tenant_seller', 'cust_tenant_contact', 'cust_tenant_followup', 'cust_tenant_health'],
            'work_orders' => ['wo_tenant_assigned', 'wo_tenant_priority', 'wo_branch', 'wo_sla_policy'],
            'equipments' => ['eq_tenant_active', 'eq_tenant_calib', 'eq_tenant_status'],
            'accounts_receivable' => ['ar_work_order', 'ar_tenant_paid'],
            'accounts_payable' => ['ap_chart_account'],
            'expenses' => ['exp_work_order', 'exp_created_by', 'exp_category'],
            'schedules' => ['sched_tenant_status', 'sched_tenant_customer', 'sched_work_order'],
            'time_entries' => ['te_work_order'],
            'commission_events' => ['ce_work_order', 'ce_tenant_created'],
            'commission_settlements' => ['cs_tenant_status'],
        ];

        foreach ($drops as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach ($indexes as $indexName) {
                    try {
                        $t->dropIndex($indexName);
                    } catch (Throwable) {
                        // Index may not exist
                    }
                }
            });
        }
    }
};
