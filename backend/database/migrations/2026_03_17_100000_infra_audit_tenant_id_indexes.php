<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infra audit: add missing tenant_id indexes.
 *
 * Multi-tenant systems ALWAYS filter by tenant_id via global scope.
 * Without an index, every query does a full table scan.
 * These 99 tables had tenant_id column but no index covering it.
 */
return new class extends Migration
{
    private array $tables = [
        'api_keys',
        'audit_blockchain_hashes',
        'automation_rules',
        'auxiliary_tools',
        'bank_statement_entries',
        'bank_statements',
        'calibration_readings',
        'candidates',
        'central_notification_prefs',
        'certificate_signatures',
        'certificate_templates',
        'checklist_submissions',
        'checklists',
        'collection_actions',
        'collection_rules',
        'commission_campaigns',
        'commission_splits',
        'continuous_feedback',
        'contract_addendums',
        'contract_adjustments',
        'contract_measurements',
        'corrective_actions',
        'cost_centers',
        'crm_email_threads',
        'crm_external_leads',
        'crm_interactive_proposals',
        'customer_complaints',
        'customer_documents',
        'debt_renegotiations',
        'departments',
        'ecological_disposals',
        'email_activities',
        'email_notes',
        'email_signatures',
        'email_tags',
        'email_templates',
        'employee_benefits',
        'employee_documents',
        'excentricity_tests',
        'expense_categories',
        'fleet_telemetry',
        'fleet_vehicles',
        'follow_ups',
        'gamification_user_badges',
        'geofence_locations',
        'inmetro_competitors',
        'inventories',
        'inventory_counts',
        'job_postings',
        'journey_entries',
        'journey_rules',
        'lab_logbook_entries',
        'measurement_uncertainties',
        'non_conformances',
        'onboarding_checklists',
        'onboarding_processes',
        'onboarding_templates',
        'partial_payments',
        'payments',
        'performance_reviews',
        'positions',
        'price_histories',
        'price_tables',
        'purchase_quotations',
        'qa_alerts',
        'quality_audits',
        'quality_procedures',
        'raw_data_backups',
        'recall_logs',
        'recurring_contracts',
        'repeatability_tests',
        'retention_samples',
        'route_plans',
        'routes_planning',
        'satisfaction_surveys',
        'scheduled_report_exports',
        'scheduled_reports',
        'service_checklists',
        'skills',
        'sla_policies',
        'stock_transfers',
        'system_revisions',
        'tech_cash_advances',
        'tool_calibrations',
        'tool_inventories',
        'traffic_fines',
        'training_courses',
        'training_enrollments',
        'trainings',
        'user_tenants',
        'users',
        'vacation_balances',
        'vehicle_inspections',
        'virtual_cards',
        'warehouses',
        'webhook_configs',
        'whatsapp_configs',
        'work_order_chats',
        'work_order_checklist_responses',
        'work_order_recurrences',
        'work_order_signatures',
        'work_order_time_logs',
        'work_schedules',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $indexName = substr($table, 0, 50).'_tid_idx';

            Schema::table($table, function (Blueprint $t) use ($indexName) {
                try {
                    $t->index('tenant_id', $indexName);
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

            $indexName = substr($table, 0, 50).'_tid_idx';

            Schema::table($table, function (Blueprint $t) use ($indexName) {
                try {
                    $t->dropIndex($indexName);
                } catch (Throwable) {
                }
            });
        }
    }
};
