<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // invoices — only has unique(tenant_id, invoice_number)
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'inv_tenant_status');
                $table->index(['tenant_id', 'customer_id'], 'inv_tenant_customer');
                $table->index(['tenant_id', 'due_date'], 'inv_tenant_due');
            });
        }

        // quotes — only has (tenant_id, created_at)
        if (Schema::hasTable('quotes')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'qt_tenant_status');
                $table->index(['tenant_id', 'customer_id'], 'qt_tenant_customer');
                $table->index(['tenant_id', 'seller_id'], 'qt_tenant_seller');
            });
        }

        // products — has (tenant_id, code) unique and (tenant_id, name)
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['tenant_id', 'category_id'], 'prod_tenant_category');
            });
        }

        // stock_movements — has (tenant_id, type) and (tenant_id, created_at)
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->index(['tenant_id', 'product_id'], 'stk_mov_tenant_product');
                $table->index(['tenant_id', 'warehouse_id'], 'stk_mov_tenant_warehouse');
            });
        }

        // crm_deals — has (tenant_id, customer_id)
        if (Schema::hasTable('crm_deals')) {
            Schema::table('crm_deals', function (Blueprint $table) {
                $table->index(['tenant_id', 'assigned_to'], 'crm_deals_tenant_assigned');
                $table->index(['tenant_id', 'stage_id'], 'crm_deals_tenant_stage');
            });
        }

        // warehouses — zero indexes
        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->index('tenant_id', 'wh_tenant');
            });
        }

        // warehouse_stocks — only has unique(warehouse_id, product_id)
        if (Schema::hasTable('warehouse_stocks')) {
            Schema::table('warehouse_stocks', function (Blueprint $table) {
                $table->index('product_id', 'ws_product');
            });
        }

        // service_calls — add driver_id index (technician + customer already covered)
        if (Schema::hasTable('service_calls') && Schema::hasColumn('service_calls', 'driver_id')) {
            Schema::table('service_calls', function (Blueprint $table) {
                $table->index(['tenant_id', 'driver_id'], 'sc_tenant_driver');
            });
        }

        // accounts_receivable — add composite (status, due_date) for overdue queries
        if (Schema::hasTable('accounts_receivable')) {
            Schema::table('accounts_receivable', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'due_date'], 'ar_tenant_status_due');
            });
        }

        // accounts_payable — add status + due_date for payables dashboard
        if (Schema::hasTable('accounts_payable')) {
            Schema::table('accounts_payable', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'due_date'], 'ap_tenant_status_due');
            });
        }

        // payments — frequently joined with payable_type
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['tenant_id', 'payable_type', 'payable_id'], 'pay_tenant_payable');
                $table->index(['tenant_id', 'payment_date'], 'pay_tenant_date');
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'invoices' => ['inv_tenant_status', 'inv_tenant_customer', 'inv_tenant_due'],
            'quotes' => ['qt_tenant_status', 'qt_tenant_customer', 'qt_tenant_seller'],
            'products' => ['prod_tenant_category'],
            'stock_movements' => ['stk_mov_tenant_product', 'stk_mov_tenant_warehouse'],
            'crm_deals' => ['crm_deals_tenant_assigned', 'crm_deals_tenant_stage'],
            'warehouses' => ['wh_tenant'],
            'warehouse_stocks' => ['ws_product'],
            'service_calls' => ['sc_tenant_driver'],
            'accounts_receivable' => ['ar_tenant_status_due'],
            'accounts_payable' => ['ap_tenant_status_due'],
            'payments' => ['pay_tenant_payable', 'pay_tenant_date'],
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
                    }
                }
            });
        }
    }
};
