<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2E — DATA-NEW-006 / DATA-001 (reaberto)
 *
 * Wave 2C fechou ~292 tabelas, mas a heurística usada na época apenas
 * detectou tabelas SEM nenhum índice tocando `tenant_id`. Tabelas que
 * já possuíam índice composto começando por OUTRA coluna (ex.:
 * `(work_order_id, tenant_id)`) ou apenas índice por outras colunas
 * permaneceram sem índice individual em `tenant_id` — full table scan
 * em queries puras por tenant continua acontecendo.
 *
 * A Rodada 3 (data-expert) identificou 52 tabelas órfãs adicionais que
 * carregam `tenant_id` mas não têm NENHUM índice cujo conjunto inclua
 * `tenant_id` em primeira posição utilizável. Esta migration cria o
 * índice individual `tenant_id` em cada uma — custo baixo, cobertura
 * total para queries lançadas pelo trait BelongsToTenant.
 *
 * Idempotente (regra H3): guards `hasTable`, `hasColumn`, `indexExists`.
 * Migration cria índices apenas onde não existem; pula em conflitos.
 */
return new class extends Migration
{
    /**
     * 52 tabelas órfãs identificadas pelo data-expert R3 (2026-04-17).
     * Verificação reproduzível: parser do schema dump conta tabelas com
     * coluna `tenant_id` declarada e exclui as que já possuem índice
     * (regular, UNIQUE ou inline) tocando `tenant_id`.
     */
    private const TABLES = [
        'accounts_payable',
        'audit_logs',
        'calibration_standard_weight',
        'cameras',
        'candidates',
        'central_item_dependencies',
        'central_item_watchers',
        'crm_deal_products',
        'crm_sequences',
        'crm_tracking_events',
        'customer_contacts',
        'data_export_jobs',
        'debt_renegotiation_items',
        'email_email_tag',
        'employee_documents',
        'equipment_documents',
        'equipment_maintenances',
        'equipment_model_product',
        'expense_status_history',
        'fuel_logs',
        'inmetro_instruments',
        'inventory_items',
        'journey_entries',
        'kiosk_sessions',
        'lgpd_anonymization_logs',
        'lgpd_consent_logs',
        'lgpd_data_requests',
        'lgpd_data_treatments',
        'lgpd_security_incidents',
        'purchase_quotation_items',
        'quote_equipments',
        'quote_items',
        'quote_photos',
        'quote_quote_tag',
        'recurring_contract_items',
        'saas_subscriptions',
        'service_call_equipments',
        'service_skills',
        'stock_demand_forecasts',
        'support_tickets',
        'technician_cash_transactions',
        'user_2fa',
        'vacation_balances',
        'vehicle_accidents',
        'vehicle_pool_requests',
        'whatsapp_messages',
        'work_order_attachments',
        'work_order_equipments',
        'work_order_items',
        'work_order_status_history',
        'work_order_technicians',
        'work_schedules',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $indexName = "{$table}_tenant_id_idx";

            if ($this->indexExists($table, $indexName)) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $t) use ($indexName) {
                    $t->index(['tenant_id'], $indexName);
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

            $indexName = "{$table}_tenant_id_idx";

            if (! $this->indexExists($table, $indexName)) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $t) use ($indexName) {
                    $t->dropIndex($indexName);
                });
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

            return count($result) > 0;
        }

        if ($driver === 'sqlite') {
            $result = DB::select(
                "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=?",
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
