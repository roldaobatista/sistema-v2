<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infra audit v2: performance indexes for tables missing efficient lookups.
 *
 * All indexes use hasTable/hasColumn guards and try/catch for idempotency.
 * NEVER modify existing migrations — this is a new additive migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // equipments: FK customer_id used in listing queries
        if (Schema::hasTable('equipments') && Schema::hasColumn('equipments', 'tenant_id') && Schema::hasColumn('equipments', 'customer_id')) {
            Schema::table('equipments', function (Blueprint $t) {
                try {
                    $t->index(['tenant_id', 'customer_id'], 'equip_tid_cid_idx');
                } catch (Throwable) {
                }
            });
        }

        // work_order_items: filter by type (product vs service)
        if (Schema::hasTable('work_order_items') && Schema::hasColumn('work_order_items', 'work_order_id') && Schema::hasColumn('work_order_items', 'type')) {
            Schema::table('work_order_items', function (Blueprint $t) {
                try {
                    $t->index(['work_order_id', 'type'], 'woi_woid_type_idx');
                } catch (Throwable) {
                }
            });
        }

        // work_order_status_history: timeline sorted by date
        if (Schema::hasTable('work_order_status_history') && Schema::hasColumn('work_order_status_history', 'work_order_id') && Schema::hasColumn('work_order_status_history', 'created_at')) {
            Schema::table('work_order_status_history', function (Blueprint $t) {
                try {
                    $t->index(['work_order_id', 'created_at'], 'wosh_woid_cat_idx');
                } catch (Throwable) {
                }
            });
        }

        // customer_contacts: lookup primary contact
        if (Schema::hasTable('customer_contacts') && Schema::hasColumn('customer_contacts', 'customer_id') && Schema::hasColumn('customer_contacts', 'is_primary')) {
            Schema::table('customer_contacts', function (Blueprint $t) {
                try {
                    $t->index(['customer_id', 'is_primary'], 'cc_cid_prim_idx');
                } catch (Throwable) {
                }
            });
        }

        // expense_categories: active categories per tenant
        if (Schema::hasTable('expense_categories') && Schema::hasColumn('expense_categories', 'tenant_id') && Schema::hasColumn('expense_categories', 'active')) {
            Schema::table('expense_categories', function (Blueprint $t) {
                try {
                    $t->index(['tenant_id', 'active'], 'expcat_tid_act_idx');
                } catch (Throwable) {
                }
            });
        }

        // payments: financial reports by date
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'tenant_id') && Schema::hasColumn('payments', 'payment_date')) {
            Schema::table('payments', function (Blueprint $t) {
                try {
                    $t->index(['tenant_id', 'payment_date'], 'pay_tid_pdate_idx');
                } catch (Throwable) {
                }
            });
        }

        // commission_settlements: list open settlements
        if (Schema::hasTable('commission_settlements') && Schema::hasColumn('commission_settlements', 'tenant_id') && Schema::hasColumn('commission_settlements', 'status')) {
            Schema::table('commission_settlements', function (Blueprint $t) {
                try {
                    $t->index(['tenant_id', 'status'], 'comset_tid_st_idx');
                } catch (Throwable) {
                }
            });
        }

        // product_categories: active filter per tenant
        if (Schema::hasTable('product_categories') && Schema::hasColumn('product_categories', 'tenant_id') && Schema::hasColumn('product_categories', 'is_active')) {
            Schema::table('product_categories', function (Blueprint $t) {
                try {
                    $t->index(['tenant_id', 'is_active'], 'pcat_tid_act_idx');
                } catch (Throwable) {
                }
            });
        }

        // service_categories: active filter per tenant
        if (Schema::hasTable('service_categories') && Schema::hasColumn('service_categories', 'tenant_id') && Schema::hasColumn('service_categories', 'is_active')) {
            Schema::table('service_categories', function (Blueprint $t) {
                try {
                    $t->index(['tenant_id', 'is_active'], 'scat_tid_act_idx');
                } catch (Throwable) {
                }
            });
        }

        // numbering_sequences: entity lookup per tenant
        if (Schema::hasTable('numbering_sequences') && Schema::hasColumn('numbering_sequences', 'tenant_id') && Schema::hasColumn('numbering_sequences', 'entity')) {
            Schema::table('numbering_sequences', function (Blueprint $t) {
                try {
                    $t->index(['tenant_id', 'entity'], 'numseq_tid_ent_idx');
                } catch (Throwable) {
                }
            });
        }
    }

    public function down(): void
    {
        $indexes = [
            'equipments' => 'equip_tid_cid_idx',
            'work_order_items' => 'woi_woid_type_idx',
            'work_order_status_history' => 'wosh_woid_cat_idx',
            'customer_contacts' => 'cc_cid_prim_idx',
            'expense_categories' => 'expcat_tid_act_idx',
            'payments' => 'pay_tid_pdate_idx',
            'commission_settlements' => 'comset_tid_st_idx',
            'product_categories' => 'pcat_tid_act_idx',
            'service_categories' => 'scat_tid_act_idx',
            'numbering_sequences' => 'numseq_tid_ent_idx',
        ];

        foreach ($indexes as $table => $indexName) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($indexName) {
                try {
                    $t->dropIndex($indexName);
                } catch (Throwable) {
                }
            });
        }
    }
};
