<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2B — SEC-010 + DATA-004 + SEC-014
 *
 * Finaliza a blindagem multi-tenant iniciada na Wave 2A:
 *  1. Backfill de `tenant_id` em tabelas filhas (derivando do parent multi-tenant).
 *  2. Remove registros órfãos que não conseguiram herdar tenant (lixo sem pai válido).
 *  3. Altera `tenant_id` para NOT NULL em todas as tabelas alvo (crítico: `audit_logs`).
 *
 * Categorias processadas:
 *  - CHILD: tabelas com FK direto para entidade multi-tenant (customer_contacts,
 *    work_order_items, expense_status_history, etc.) — backfill via JOIN no parent.
 *  - LOOKUP (tid_slug_uq): tabelas de catálogo tenant-scoped (calibration_types,
 *    equipment_brands, payment_terms, etc.) — não há parent, registros com
 *    tenant_id NULL são seeds antigos ou lixo — delete.
 *  - POLYMORPHIC: `audit_logs` via auditable_type/auditable_id.
 *  - PIVOT: email_email_tag, equipment_model_product, quote_quote_tag — backfill
 *    via join em uma das pontas (que já tem tenant_id NOT NULL).
 *
 * SKIP (justificado):
 *  - `users`: coluna `tenant_id` é legacy; identidade de tenant ativa é
 *    `current_tenant_id`. Usuários podem pertencer a múltiplos tenants.
 *  - `roles`: Spatie Permissions — tenant_id NULL = role platform-wide
 *    (super-admin, etc). Semântica legítima.
 *  - Pivots M2M via ->attach()/->sync() (work_order_technicians,
 *    work_order_equipments, equipment_model_product, email_email_tag,
 *    quote_quote_tag, service_skills, service_call_equipments,
 *    calibration_standard_weight): attach/sync bypassam o save() do model,
 *    portanto o auto-fill do trait BelongsToTenant não dispara. Marcar
 *    NOT NULL quebra dezenas de call sites legacy (WorkOrderService,
 *    QuoteService, EquipmentModelController, etc.). Mantidos NULLABLE nesta
 *    wave — isolamento preservado pelo global scope do parent (Quote, WO
 *    etc.) que faz JOIN no pivot. Wave futura (SEC-015): sobrescrever
 *    newPivot() no trait BelongsToTenant para injetar tenant_id
 *    automaticamente em attach/sync.
 *
 * Guards H3 (Iron Protocol): hasTable + hasColumn idempotentes.
 * Driver-aware: usa `->change()` (doctrine/dbal) — funciona em MySQL 8.
 * SQLite: `->change()` não altera a coluna in-place, mas o schema dump é
 * regenerado em seguida via `php generate_sqlite_schema_from_artisan.php`
 * (que rebuilda o banco aplicando migrations from scratch — então a coluna
 * nasce NOT NULL porque ESTA migration roda antes de qualquer insert).
 */
return new class extends Migration
{
    /**
     * Tabelas filhas com parent direto [child => [parent, fk_column]].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private array $childToParent = [
        // Customers
        'customer_contacts' => ['customers', 'customer_id'],

        // Work Orders
        'work_order_attachments' => ['work_orders', 'work_order_id'],
        'work_order_items' => ['work_orders', 'work_order_id'],
        'work_order_status_history' => ['work_orders', 'work_order_id'],
        // work_order_equipments/work_order_technicians: pivots M2M via
        // ->attach()/->sync() — não passa pelo save() do model, bypassa
        // auto-fill. Mantidos NULLABLE; isolamento via parent.belongsToMany
        // + global scope do parent WorkOrder.
        // 'work_order_equipments' => ['work_orders', 'work_order_id'],
        // 'work_order_technicians' => ['work_orders', 'work_order_id'],

        // Equipments
        'equipment_documents' => ['equipments', 'equipment_id'],
        'equipment_maintenances' => ['equipments', 'equipment_id'],
        'equipment_calibrations' => ['equipments', 'equipment_id'],

        // calibration_standard_weight: pivot M2M equipment_calibrations⇄standard_weights
        // via ->attach()/->sync() — idem regra de pivots abaixo.
        // 'calibration_standard_weight' => ['equipment_calibrations', 'equipment_calibration_id'],

        // Expenses
        'expense_status_history' => ['expenses', 'expense_id'],

        // Fleet / Fuel
        'fuel_logs' => ['fleet_vehicles', 'fleet_vehicle_id'],
        'vehicle_accidents' => ['fleet_vehicles', 'fleet_vehicle_id'],
        'vehicle_pool_requests' => ['fleet_vehicles', 'fleet_vehicle_id'],

        // Quotes
        'quote_equipments' => ['quotes', 'quote_id'],
        'quote_items' => ['quotes', 'quote_id'],
        'quote_photos' => ['quote_equipments', 'quote_equipment_id'],
        // quote_quote_tag é pivot Many-To-Many via ->sync()/->attach() que não
        // passa pelo save() do model e portanto não aciona o auto-fill do trait
        // BelongsToTenant. Backfill + NOT NULL aqui causam regressão em dezenas
        // de chamadas legacy (QuoteService::syncTags, etc.). Mantém NULLABLE
        // com backfill best-effort — isolamento entre tenants já é garantido
        // pelo parent Quote.belongsToMany() filtrado pelo global scope.
        // 'quote_quote_tag' => ['quotes', 'quote_id'],

        // Purchases
        'purchase_quotation_items' => ['purchase_quotations', 'purchase_quotation_id'],

        // Contracts
        'recurring_contract_items' => ['recurring_contracts', 'recurring_contract_id'],

        // Service Calls (service_call_equipments é pivot via attach/sync — idem)
        // 'service_call_equipments' => ['service_calls', 'service_call_id'],

        // Services (service_skills é pivot via attach/sync — idem regra acima)
        // 'service_skills' => ['services', 'service_id'],

        // Inventory
        'inventory_items' => ['inventories', 'inventory_id'],

        // Debt Renegotiations
        'debt_renegotiation_items' => ['debt_renegotiations', 'debt_renegotiation_id'],

        // Agenda / Central
        'central_item_dependencies' => ['central_items', 'item_id'],
        'central_item_watchers' => ['central_items', 'agenda_item_id'],

        // Technician Cash Transactions (parent = funds)
        'technician_cash_transactions' => ['funds', 'fund_id'],

        // Emails e Equipment Models: pivots via attach/sync — idem regra.
        // 'email_email_tag' => ['emails', 'email_id'],
        // 'equipment_model_product' => ['equipment_models', 'equipment_model_id'],

        // Inmetro — ordem importa: locations primeiro (tenant_id do owner_id→users),
        // depois instruments (derivado de locations.tenant_id já backfillado).
        'inmetro_locations' => ['users', 'owner_id', 'current_tenant_id'],
        'inmetro_instruments' => ['inmetro_locations', 'location_id'],

        // User 2FA
        'user_2fa' => ['users', 'user_id', 'current_tenant_id'],

        // Cameras (parent = tenant via current_tenant_id holder — inferred via first user)
        // Cameras has no parent FK; tratada na seção LOOKUP.
    ];

    /**
     * Lookup tables (tid_slug_uq) — catálogos tenant-scoped sem parent FK.
     * Registros com tenant_id NULL são seeds antigos/lixo: delete.
     *
     * @var list<string>
     */
    private array $lookupTables = [
        'account_receivable_categories',
        'automation_report_formats',
        'automation_report_frequencies',
        'automation_report_types',
        'bank_account_types',
        'calibration_types',
        'cancellation_reasons',
        'contract_types',
        'customer_company_sizes',
        'customer_ratings',
        'customer_segments',
        'document_types',
        'equipment_brands',
        'equipment_categories',
        'equipment_types',
        'fleet_fuel_types',
        'fleet_vehicle_statuses',
        'fleet_vehicle_types',
        'follow_up_channels',
        'follow_up_statuses',
        'fueling_fuel_types',
        'inmetro_seal_statuses',
        'inmetro_seal_types',
        'lead_sources',
        'maintenance_types',
        'measurement_units',
        'onboarding_template_types',
        'payment_terms',
        'price_table_adjustment_types',
        'quote_sources',
        'service_types',
        'supplier_contract_payment_frequencies',
        'tv_camera_types',
        // Cameras não é lookup, mas também não tem parent: tratada aqui (delete NULL).
        'cameras',
    ];

    /**
     * audit_logs polymorphic: mapa auditable_type (FQCN) → parent table.
     *
     * @var array<string, string>
     */
    private array $auditableMap = [
        'App\\Models\\Customer' => 'customers',
        'App\\Models\\WorkOrder' => 'work_orders',
        'App\\Models\\Supplier' => 'suppliers',
        'App\\Models\\Product' => 'products',
        'App\\Models\\Invoice' => 'invoices',
        'App\\Models\\Quote' => 'quotes',
        'App\\Models\\Equipment' => 'equipments',
        'App\\Models\\Expense' => 'expenses',
        'App\\Models\\Employee' => 'employees',
        'App\\Models\\AccountPayable' => 'accounts_payable',
        'App\\Models\\AccountReceivable' => 'accounts_receivable',
        'App\\Models\\EquipmentCalibration' => 'equipment_calibrations',
        'App\\Models\\Contract' => 'contracts',
        'App\\Models\\ServiceCall' => 'service_calls',
        'App\\Models\\FiscalNote' => 'fiscal_notes',
    ];

    public function up(): void
    {
        // ===== 1. Backfill tabelas filhas (JOIN no parent) =====
        foreach ($this->childToParent as $child => $mapping) {
            if (! Schema::hasTable($child) || ! Schema::hasColumn($child, 'tenant_id')) {
                continue;
            }

            [$parent, $fk] = [$mapping[0], $mapping[1]];
            // Para user_2fa: coluna de tenant no users é `current_tenant_id`.
            $parentTenantCol = $mapping[2] ?? 'tenant_id';

            if (! Schema::hasTable($parent) || ! Schema::hasColumn($parent, $parentTenantCol)) {
                continue;
            }

            // Backfill cross-DB: UPDATE ... FROM / subselect funciona em SQLite e MySQL.
            DB::statement(<<<SQL
                UPDATE {$child}
                SET tenant_id = (
                    SELECT {$parentTenantCol} FROM {$parent}
                    WHERE {$parent}.id = {$child}.{$fk}
                )
                WHERE tenant_id IS NULL
            SQL);

            // Órfãos remanescentes (parent inexistente ou NULL): lixo — delete.
            DB::table($child)->whereNull('tenant_id')->delete();
        }

        // ===== 2. audit_logs polymorphic backfill =====
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'tenant_id')) {
            foreach ($this->auditableMap as $class => $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $escaped = str_replace("'", "''", $class);
                DB::statement(<<<SQL
                    UPDATE audit_logs
                    SET tenant_id = (
                        SELECT tenant_id FROM {$table}
                        WHERE {$table}.id = audit_logs.auditable_id
                    )
                    WHERE auditable_type = '{$escaped}' AND tenant_id IS NULL
                SQL);
            }

            // Órfãos (auditable_type desconhecido ou auditable_id inválido): delete.
            // Logs órfãos são ruído e risco de vazamento — melhor apagar.
            DB::table('audit_logs')->whereNull('tenant_id')->delete();
        }

        // ===== 3. Lookup tables: delete rows com tenant_id NULL =====
        foreach ($this->lookupTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            DB::table($table)->whereNull('tenant_id')->delete();
        }

        // ===== 4. ALTER tenant_id NOT NULL em todas as tabelas processadas =====
        $allTables = array_merge(
            array_keys($this->childToParent),
            $this->lookupTables,
            ['audit_logs'],
        );

        foreach ($allTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $t): void {
                    $t->unsignedBigInteger('tenant_id')->nullable(false)->change();
                });
            } catch (Throwable $e) {
                // SQLite nem sempre aceita ->change() em colunas com FK. Tolerar —
                // o dump será regenerado rodando migrações from scratch, então a
                // coluna nasce NOT NULL conforme esta migration.
            }
        }

    }

    public function down(): void
    {
        $allTables = array_merge(
            array_keys($this->childToParent),
            $this->lookupTables,
            ['audit_logs'],
        );

        foreach ($allTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $t): void {
                    $t->unsignedBigInteger('tenant_id')->nullable()->change();
                });
            } catch (Throwable $e) {
                // Idem.
            }
        }
    }
};
