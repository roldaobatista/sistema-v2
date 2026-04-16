<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Infra audit v3: performance indexes for tables missing efficient lookups on foreign keys.
 *
 * All indexes use hasTable/hasColumn guards and try/catch for idempotency.
 * NEVER modify existing migrations — this is a new additive migration.
 */
return new class extends Migration
{
    private function canSafelyIndexColumn(string $table, string $column): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return true;
        }

        $database = Schema::getConnection()->getDatabaseName();

        if ($database === '') {
            return true;
        }

        $columnDefinition = DB::table('information_schema.COLUMNS')
            ->select('DATA_TYPE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->first();

        $dataType = is_object($columnDefinition) && isset($columnDefinition->DATA_TYPE)
            ? strtolower((string) $columnDefinition->DATA_TYPE)
            : null;

        return ! in_array($dataType, ['text', 'tinytext', 'mediumtext', 'longtext', 'blob', 'tinyblob', 'mediumblob', 'longblob'], true);
    }

    public function up(): void
    {
        $indexesToAdd = [
            'bank_statement_entries' => ['matched_id'],
            'biometric_configs' => ['device_id'],
            'capa_records' => ['source_id'],
            'certificate_signatures' => ['certificate_id'],
            'crm_calendar_events' => ['external_id'],
            'crm_external_leads' => ['tax_id'],
            'fiscal_notes' => ['provider_id'],
            'fuel_logs' => ['driver_id'],
            'gamification_user_badges' => ['tenant_id'],
            'marketplace_requests' => ['partner_id'],
            'mobile_notifications' => ['entity_id'],
            'onboarding_processes' => ['template_id'],
            'online_payments' => ['gateway_id'],
            'parts_kit_items' => ['reference_id'],
            'print_jobs' => ['document_id'],
            'quality_corrective_actions' => ['responsible_id'],
            'reconciliation_rules' => ['target_id'],
            'sso_configurations' => ['client_id'],
            'stock_disposal_items' => ['batch_id'],
            'sync_queue' => ['entity_id'],
            'user_sessions' => ['token_id'],
            'vehicle_accidents' => ['driver_id'],
            'vehicle_pool_requests' => ['fleet_vehicle_id'],
            'work_order_items' => ['reference_id'],
        ];

        foreach ($indexesToAdd as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column) && $this->canSafelyIndexColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $t) use ($column) {
                        try {
                            $t->index([$column]);
                        } catch (Throwable $e) {
                            // Ignore if index already exists
                        }
                    });
                }
            }
        }
    }

    public function down(): void
    {
        $indexesToRemove = [
            'bank_statement_entries' => ['matched_id'],
            'biometric_configs' => ['device_id'],
            'capa_records' => ['source_id'],
            'certificate_signatures' => ['certificate_id'],
            'crm_calendar_events' => ['external_id'],
            'crm_external_leads' => ['tax_id'],
            'fiscal_notes' => ['provider_id'],
            'fuel_logs' => ['driver_id'],
            'gamification_user_badges' => ['tenant_id'],
            'marketplace_requests' => ['partner_id'],
            'mobile_notifications' => ['entity_id'],
            'onboarding_processes' => ['template_id'],
            'online_payments' => ['gateway_id'],
            'parts_kit_items' => ['reference_id'],
            'print_jobs' => ['document_id'],
            'quality_corrective_actions' => ['responsible_id'],
            'reconciliation_rules' => ['target_id'],
            'sso_configurations' => ['client_id'],
            'stock_disposal_items' => ['batch_id'],
            'sync_queue' => ['entity_id'],
            'user_sessions' => ['token_id'],
            'vehicle_accidents' => ['driver_id'],
            'vehicle_pool_requests' => ['fleet_vehicle_id'],
            'work_order_items' => ['reference_id'],
        ];

        foreach ($indexesToRemove as $table => $columns) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($columns) {
                    foreach ($columns as $column) {
                        try {
                            $t->dropIndex([$column]);
                        } catch (Throwable $e) {
                            // Ignore missing indexes on down
                        }
                    }
                });
            }
        }
    }
};
