<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes on deleted_at for all high-traffic soft-delete tables.
 *
 * Laravel's SoftDeletes trait adds "WHERE deleted_at IS NULL" to every query.
 * Without an index, this becomes a full table scan on large tables.
 * On tables already covered by a composite index that includes tenant_id,
 * we add (tenant_id, deleted_at) for optimal multi-tenant filtering.
 */
return new class extends Migration
{
    /**
     * High-traffic tables that use softDeletes.
     * Format: table => [index_name, columns]
     *
     * For tables with tenant_id, the composite (tenant_id, deleted_at) is optimal
     * because almost all queries are tenant-scoped + soft-delete filtered.
     */
    private array $indexes = [
        'work_orders' => ['wo_deleted_at', ['tenant_id', 'deleted_at']],
        'customers' => ['cust_deleted_at', ['tenant_id', 'deleted_at']],
        'equipments' => ['equip_deleted_at', ['tenant_id', 'deleted_at']],
        'quotes' => ['qt_deleted_at', ['tenant_id', 'deleted_at']],
        'service_calls' => ['sc_deleted_at', ['tenant_id', 'deleted_at']],
        'accounts_receivable' => ['ar_deleted_at', ['tenant_id', 'deleted_at']],
        'accounts_payable' => ['ap_deleted_at', ['tenant_id', 'deleted_at']],
        'expenses' => ['exp_deleted_at', ['tenant_id', 'deleted_at']],
        'crm_deals' => ['deals_deleted_at', ['tenant_id', 'deleted_at']],
        'central_items' => ['ci_deleted_at', ['tenant_id', 'deleted_at']],
        'invoices' => ['inv_deleted_at', ['tenant_id', 'deleted_at']],
        'suppliers' => ['sup_deleted_at', ['tenant_id', 'deleted_at']],
        'products' => ['prod_deleted_at', ['tenant_id', 'deleted_at']],
        'recurring_contracts' => ['rcon_deleted_at', ['tenant_id', 'deleted_at']],
        'bank_accounts' => ['ba_deleted_at', ['tenant_id', 'deleted_at']],
        'fund_transfers' => ['ft_deleted_at', ['tenant_id', 'deleted_at']],
        'schedules' => ['sched_deleted_at', ['deleted_at']],
        'time_entries' => ['te_deleted_at', ['deleted_at']],
        'expense_categories' => ['ec_deleted_at', ['deleted_at']],
        'warehouses' => ['wh_deleted_at', ['tenant_id', 'deleted_at']],
        'work_order_templates' => ['wot_deleted_at', ['tenant_id', 'deleted_at']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => [$indexName, $columns]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Check all columns exist
            $allExist = true;
            foreach ($columns as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    $allExist = false;
                    break;
                }
            }

            if (! $allExist) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($indexName, $columns) {
                try {
                    $t->index($columns, $indexName);
                } catch (Throwable) {
                    // Index may already exist
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => [$indexName, $columns]) {
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
