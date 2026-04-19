<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2D — DATA-002
 *
 * Adiciona índice individual em `deleted_at` em ~113 tabelas que possuem
 * a coluna `deleted_at` (SoftDeletes) mas não têm índice cobrindo-a.
 *
 * Como `SoftDeletes` global scope injeta `WHERE deleted_at IS NULL` em
 * toda query Eloquent, a ausência de índice causa avaliação da condição
 * em todas as linhas — degradação de performance proporcional ao volume.
 *
 * Decisão de design (índice individual, não composto com tenant_id):
 *   - Adicionar `(tenant_id, deleted_at)` em todas as 113 tabelas teria
 *     custo elevado em writes e duplicaria com o índice de tenant_id já
 *     presente (Wave 2C). Índice individual em `deleted_at` é o mínimo
 *     necessário para evitar full-scan na verificação de soft delete.
 *   - Tabelas hot-path (accounts_payable, accounts_receivable, etc.)
 *     podem receber índice composto futuramente em wave dedicada se o
 *     EXPLAIN do MySQL 8 indicar necessidade.
 *
 * Idempotente (regra H3): guards `hasTable`, `hasColumn`, `indexExists`.
 * Migration cria índices apenas onde não existem; pula em conflitos.
 */
return new class extends Migration
{
    /**
     * Lista de tabelas a receber índice `{table}_deleted_at_idx`.
     * Lista identificada por análise programática do schema dump:
     * tabelas com coluna `deleted_at` ausentes da lista de índices
     * que mencionam `deleted_at`.
     */
    private const TABLES = [
        'account_payable_categories',
        'account_receivable_categories',
        'accounts_payable',
        'accounts_receivable',
        'asset_records',
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
        'commission_rules',
        'contract_types',
        'contracts',
        'cost_centers',
        'crm_deals',
        'crm_funnel_automations',
        'crm_pipelines',
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
        'fiscal_invoices',
        'fiscal_notes',
        'fleet_fuel_types',
        'fleet_vehicle_statuses',
        'fleet_vehicle_types',
        'fleet_vehicles',
        'fleets',
        'follow_up_channels',
        'follow_up_statuses',
        'fueling_fuel_types',
        'fueling_logs',
        'fund_transfers',
        'hour_bank_policies',
        'inmetro_competitors',
        'inmetro_owners',
        'inmetro_seal_statuses',
        'inmetro_seal_types',
        'inmetro_seals',
        'inventories',
        'invoices',
        'journey_blocks',
        'journey_days',
        'journey_entries',
        'journey_policies',
        'journey_rules',
        'lead_sources',
        'maintenance_types',
        'material_requests',
        'measurement_units',
        'non_conformities',
        'onboarding_template_types',
        'parts_kits',
        'payment_terms',
        'price_table_adjustment_types',
        'price_tables',
        'product_serials',
        'products',
        'projects',
        'purchase_quotations',
        'purchase_quotes',
        'quality_procedures',
        'quote_sources',
        'quotes',
        'recurring_contracts',
        'repair_seal_batches',
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
        'surveys',
        'technician_certifications',
        'tenants',
        'time_entries',
        'tool_checkouts',
        'tool_inventories',
        'travel_requests',
        'tv_camera_types',
        'users',
        'vehicle_insurances',
        'vehicle_tires',
        'warehouses',
        'webhooks',
        'work_order_recurrences',
        'work_order_templates',
        'work_orders',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            $indexName = "{$table}_deleted_at_idx";

            if ($this->indexExists($table, $indexName)) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $t) use ($indexName) {
                    $t->index(['deleted_at'], $indexName);
                });
            } catch (Throwable $e) {
                // Ignora colisão de nome / índice já existente sob outro nome
                if (! $this->isAlreadyExistsError($e)) {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $indexName = "{$table}_deleted_at_idx";

            if (! $this->indexExists($table, $indexName)) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $t) use ($indexName) {
                    $t->dropIndex($indexName);
                });
            } catch (Throwable $e) {
                // Ignora se índice foi removido fora desta migration
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $result = DB::select(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
                [$table, $indexName]
            );

            return count($result) > 0;
        }

        if ($driver === 'mysql') {
            $result = DB::select(
                'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$table, $indexName]
            );

            return count($result) > 0;
        }

        if ($driver === 'pgsql') {
            $result = DB::select(
                'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            );

            return count($result) > 0;
        }

        return false;
    }

    private function isAlreadyExistsError(Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'already exists')
            || str_contains($msg, 'duplicate key name')
            || str_contains($msg, 'duplicate index');
    }
};
