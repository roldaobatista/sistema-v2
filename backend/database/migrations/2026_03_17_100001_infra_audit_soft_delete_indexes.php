<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infra audit: add missing deleted_at indexes for soft-delete tables.
 *
 * Laravel's SoftDeletes trait adds `whereNull('deleted_at')` to every query.
 * Without an index, this forces a full table scan on every read operation.
 * These 90 tables use soft deletes but had no index on deleted_at.
 */
return new class extends Migration
{
    private array $tables = [
        'account_payable_categories',
        'account_receivable_categories',
        'accounts_payable',
        'accounts_receivable',
        'auto_assignment_rules',
        'automation_report_formats',
        'automation_report_frequencies',
        'automation_report_types',
        'automation_rules',
        'auxiliary_tools',
        'bank_account_types',
        'bank_accounts',
        'batches',
        'calibration_types',
        'cancellation_reasons',
        'central_items',
        'commission_events',
        'contract_types',
        'contracts',
        'cost_centers',
        'crm_deals',
        'crm_sequences',
        'crm_territories',
        'crm_web_forms',
        'customer_company_sizes',
        'customer_ratings',
        'customer_segments',
        'customers',
        'document_types',
        'document_versions',
        'equipment_brands',
        'equipment_categories',
        'equipment_types',
        'equipments',
        'expense_categories',
        'expenses',
        'financial_checks',
        'fleet_fuel_types',
        'fleet_vehicle_statuses',
        'fleet_vehicle_types',
        'fleet_vehicles',
        'follow_up_channels',
        'follow_up_statuses',
        'fueling_fuel_types',
        'fueling_logs',
        'fund_transfers',
        'inmetro_seal_statuses',
        'inmetro_seal_types',
        'inmetro_seals',
        'inventories',
        'invoices',
        'lead_sources',
        'maintenance_types',
        'material_requests',
        'measurement_units',
        'onboarding_template_types',
        'parts_kits',
        'payment_terms',
        'price_table_adjustment_types',
        'price_tables',
        'product_serials',
        'products',
        'purchase_quotations',
        'purchase_quotes',
        'quality_procedures',
        'quote_sources',
        'quotes',
        'recurring_contracts',
        'rma_requests',
        'schedules',
        'service_calls',
        'service_types',
        'services',
        'standard_weights',
        'stock_disposals',
        'stock_movements',
        'supplier_contract_payment_frequencies',
        'supplier_contracts',
        'suppliers',
        'time_entries',
        'tool_checkouts',
        'tool_inventories',
        'tv_camera_types',
        'vehicle_insurances',
        'vehicle_tires',
        'warehouses',
        'work_order_recurrences',
        'work_order_templates',
        'work_orders',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            $indexName = substr($table, 0, 50).'_del_idx';

            Schema::table($table, function (Blueprint $t) use ($indexName) {
                try {
                    $t->index('deleted_at', $indexName);
                } catch (Throwable) {
                    // Index already exists
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $indexName = substr($table, 0, 50).'_del_idx';

            Schema::table($table, function (Blueprint $t) use ($indexName) {
                try {
                    $t->dropIndex($indexName);
                } catch (Throwable) {
                }
            });
        }
    }
};
