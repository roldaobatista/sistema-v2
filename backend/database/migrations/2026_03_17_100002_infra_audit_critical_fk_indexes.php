<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infra audit: add indexes on critical FK columns.
 *
 * Foreign key columns used in JOINs and WHERE clauses need indexes
 * for acceptable query performance. This migration covers the most
 * impactful FK columns from high-traffic tables.
 */
return new class extends Migration
{
    /**
     * Table => [columns to index]
     * Only includes high-traffic tables and frequently joined FKs.
     */
    private array $indexes = [
        // Work orders core - most queried entity
        'work_orders' => ['equipment_id', 'branch_id', 'quote_id', 'service_call_id', 'seller_id', 'driver_id', 'recurring_contract_id', 'parent_id', 'fleet_vehicle_id', 'cost_center_id'],
        'work_order_items' => ['work_order_id', 'reference_id', 'warehouse_id'],
        'work_order_equipments' => ['work_order_id', 'equipment_id'],
        'work_order_status_history' => ['work_order_id', 'user_id'],
        'work_order_events' => ['user_id'],
        'work_order_ratings' => ['work_order_id', 'customer_id'],

        // Financial - high query volume
        'accounts_payable' => ['category_id', 'supplier_id', 'chart_of_account_id', 'cost_center_id'],
        'accounts_receivable' => ['work_order_id', 'chart_of_account_id'],
        'partial_payments' => ['account_receivable_id'],
        'invoices' => ['work_order_id', 'customer_id'],
        'fund_transfers' => ['bank_account_id'],
        'debt_renegotiations' => ['customer_id'],
        'debt_renegotiation_items' => ['debt_renegotiation_id', 'account_receivable_id'],

        // Customers & CRM - frequent lookups
        'customer_contacts' => ['customer_id'],
        'customer_documents' => ['customer_id'],
        'customer_complaints' => ['customer_id', 'work_order_id'],
        'crm_deals' => ['quote_id', 'work_order_id', 'equipment_id'],
        'crm_messages' => ['user_id'],
        'crm_pipeline_stages' => ['pipeline_id'],
        'crm_web_form_submissions' => ['customer_id', 'deal_id'],

        // Quotes - frequent joins
        'quote_items' => ['product_id', 'service_id'],
        'quote_equipments' => ['quote_id', 'equipment_id'],
        'quotes' => ['seller_id', 'parent_quote_id'],

        // Services & schedules
        'schedules' => ['work_order_id', 'customer_id'],
        'service_calls' => ['quote_id', 'technician_id', 'contract_id'],
        'service_call_comments' => ['user_id'],

        // Stock & products
        'stock_movements' => ['work_order_id', 'warehouse_id'],
        'stock_transfers' => ['from_warehouse_id', 'to_warehouse_id'],
        'stock_transfer_items' => ['stock_transfer_id', 'product_id'],
        'products' => ['category_id'],
        'product_serials' => ['product_id', 'warehouse_id'],

        // Equipment & calibration
        'equipments' => ['equipment_model_id'],
        'equipment_calibrations' => ['work_order_id'],

        // Commission
        'commission_events' => ['commission_rule_id', 'work_order_id'],
        'commission_splits' => ['user_id'],

        // HR
        'leave_requests' => ['user_id'],
        'performance_reviews' => ['user_id', 'reviewer_id'],
        'employee_benefits' => ['user_id'],

        // Email
        'email_activities' => ['email_id', 'user_id'],

        // Users
        'users' => ['current_tenant_id', 'branch_id', 'department_id', 'position_id'],

        // Contracts
        'contracts' => ['customer_id'],
        'contract_addendums' => ['contract_id'],
        'recurring_contract_items' => ['recurring_contract_id'],

        // Imports
        'imports' => ['user_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        continue;
                    }

                    $indexName = substr($table, 0, 40).'_'.substr($column, 0, 20).'_fk_idx';

                    try {
                        $t->index($column, $indexName);
                    } catch (Throwable) {
                        // Index already exists
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $column) {
                    $indexName = substr($table, 0, 40).'_'.substr($column, 0, 20).'_fk_idx';

                    try {
                        $t->dropIndex($indexName);
                    } catch (Throwable) {
                    }
                }
            });
        }
    }
};
