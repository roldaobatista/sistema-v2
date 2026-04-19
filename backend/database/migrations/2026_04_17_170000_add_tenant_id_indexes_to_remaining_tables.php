<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2C — DATA-001 + SEC-011
 *
 * Adiciona índice individual em `tenant_id` em ~292 tabelas que ainda não
 * possuem índice cujo PRIMEIRO campo seja `tenant_id`.
 *
 * Como `BelongsToTenant` injeta `WHERE tenant_id = ?` em toda query,
 * a ausência de índice causa full table scan por tenant — degradação
 * de performance e potencial amplificador de DoS.
 *
 * Idempotente (regra H3): guards `hasTable`, `hasColumn`, `indexExists`.
 * Migration cria índices apenas onde não existem; pula em conflitos.
 */
return new class extends Migration
{
    /**
     * Lista de tabelas a receber índice `{table}_tenant_id_idx`.
     * Lista identificada por análise programática do schema dump
     * (CREATE INDEX começando por tenant_id ausente).
     */
    private const TABLES = [
        'access_time_restrictions',
        'account_payable_categories',
        'account_payable_installments',
        'account_payable_payments',
        'account_plans',
        'account_receivable_installments',
        'accounts_receivable',
        'admissions',
        'analytics_datasets',
        'api_keys',
        'asset_disposals',
        'asset_inventories',
        'asset_movements',
        'asset_tags',
        'audit_blockchain_hashes',
        'auto_assignment_rules',
        'automation_rules',
        'auvo_imports',
        'auxiliary_tools',
        'bank_accounts',
        'bank_statement_entries',
        'bank_statements',
        'batches',
        'branches',
        'calibration_readings',
        'capa_records',
        'central_attachments',
        'central_notification_prefs',
        'central_rules',
        'central_subtasks',
        'central_templates',
        'central_time_entries',
        'certificate_signatures',
        'certificate_templates',
        'chat_messages',
        'checklist_submissions',
        'checklists',
        'clt_violations',
        'collection_action_logs',
        'collection_actions',
        'collection_logs',
        'collection_rules',
        'commission_campaigns',
        'commission_disputes',
        'commission_events',
        'commission_rules',
        'commission_splits',
        'commitments',
        'contact_policies',
        'continuous_feedback',
        'contract_addendums',
        'contract_adjustments',
        'contract_measurements',
        'contracts',
        'corrective_actions',
        'cost_centers',
        'crm_activities',
        'crm_calendar_events',
        'crm_contract_renewals',
        'crm_deal_stage_histories',
        'crm_deals',
        'crm_email_threads',
        'crm_external_leads',
        'crm_follow_up_tasks',
        'crm_forecast_snapshots',
        'crm_interactive_proposals',
        'crm_lead_scoring_rules',
        'crm_loss_reasons',
        'crm_messages',
        'crm_pipeline_stages',
        'crm_referrals',
        'crm_sales_goals',
        'crm_sequence_enrollments',
        'crm_smart_alerts',
        'crm_territories',
        'customer_addresses',
        'customer_complaints',
        'customer_documents',
        'customer_locations',
        'data_masking_rules',
        'debt_renegotiations',
        'departments',
        'document_versions',
        'ecological_disposals',
        'email_activities',
        'email_campaigns',
        'email_logs',
        'email_notes',
        'email_rules',
        'email_signatures',
        'email_tags',
        'email_templates',
        'emails',
        'embedded_dashboards',
        'employee_benefits',
        'epi_records',
        'equipment_models',
        'equipments',
        'erp_sync_logs',
        'escalation_rules',
        'esocial_certificates',
        'esocial_events',
        'espelho_confirmations',
        'excentricity_tests',
        'expense_categories',
        'expenses',
        'export_jobs',
        'financial_checks',
        'fiscal_audit_logs',
        'fiscal_events',
        'fiscal_invoice_items',
        'fiscal_notes',
        'fiscal_scheduled_emissions',
        'fiscal_templates',
        'fiscal_webhooks',
        'fleet_fuel_entries',
        'fleet_maintenances',
        'fleet_telemetry',
        'fleet_trips',
        'fleet_vehicles',
        'fleets',
        'follow_ups',
        'fueling_logs',
        'fund_transfers',
        'funnel_email_automations',
        'gamification_badges',
        'gamification_user_badges',
        'geo_login_alerts',
        'geofence_locations',
        'hour_bank_transactions',
        'immutable_backups',
        'important_dates',
        'imports',
        'inmetro_competitor_snapshots',
        'inmetro_competitors',
        'inmetro_compliance_checklists',
        'inmetro_lead_interactions',
        'inmetro_lead_scores',
        'inmetro_locations',
        'inmetro_prospection_queue',
        'inmetro_snapshots',
        'inmetro_webhooks',
        'inmetro_win_loss',
        'inventories',
        'inventory_counts',
        'job_postings',
        'journey_rules',
        'knowledge_base_articles',
        'lab_logbook_entries',
        'leave_requests',
        'management_reviews',
        'marketplace_requests',
        'material_requests',
        'measurement_uncertainties',
        'model_has_permissions',
        'model_has_roles',
        'nfse_emissions',
        'non_conformances',
        'non_conformities',
        'notification_channels',
        'notifications',
        'nps_responses',
        'nps_surveys',
        'offline_map_regions',
        'onboarding_checklist_items',
        'onboarding_checklists',
        'onboarding_processes',
        'onboarding_templates',
        'online_payments',
        'partial_payments',
        'parts_kits',
        'payments',
        'payroll_lines',
        'payslips',
        'performance_reviews',
        'photo_annotations',
        'portal_guest_links',
        'portal_ticket_comments',
        'portal_tickets',
        'positions',
        'price_histories',
        'price_tables',
        'print_jobs',
        'privacy_consents',
        'project_milestones',
        'project_resources',
        'project_time_entries',
        'psei_submissions',
        'purchase_quotations',
        'purchase_quotes',
        'push_subscriptions',
        'qa_alerts',
        'quality_audits',
        'quality_corrective_actions',
        'quality_procedures',
        'quick_notes',
        'quote_approval_thresholds',
        'quote_emails',
        'quote_templates',
        'raw_data_backups',
        'recall_logs',
        'reconciliation_rules',
        'recurring_commissions',
        'recurring_contracts',
        'referral_codes',
        'repair_seal_alerts',
        'repair_seal_assignments',
        'repeatability_tests',
        'rescissions',
        'retention_samples',
        'rma_requests',
        'route_plans',
        'routes_planning',
        'rr_studies',
        'satisfaction_surveys',
        'scale_readings',
        'scheduled_appointments',
        'scheduled_report_exports',
        'scheduled_reports',
        'schedules',
        'seal_applications',
        'search_index',
        'self_service_quote_requests',
        'sensor_readings',
        'service_call_comments',
        'service_call_templates',
        'service_calls',
        'service_catalogs',
        'service_checklists',
        'skill_requirements',
        'skills',
        'sla_policies',
        'sla_violations',
        'stock_disposals',
        'stock_movements',
        'stock_transfers',
        'supplier_contracts',
        'survey_responses',
        'surveys',
        'sync_conflict_logs',
        'sync_queue',
        'sync_queue_items',
        'system_alerts',
        'system_revisions',
        'tax_calculations',
        'tech_cash_advances',
        'technician_fund_requests',
        'technician_skills',
        'ticket_categories',
        'time_clock_adjustments',
        'time_clock_audit_logs',
        'time_entries',
        'toll_transactions',
        'tool_calibrations',
        'tool_checkouts',
        'tool_inventories',
        'traffic_fines',
        'training_courses',
        'training_enrollments',
        'trainings',
        'tv_dashboard_configs',
        'used_stock_items',
        'user_competencies',
        'user_skills',
        'user_tenants',
        'vehicle_gps_positions',
        'vehicle_inspections',
        'vehicle_insurances',
        'vehicle_tires',
        'virtual_cards',
        'visit_checkins',
        'visit_reports',
        'visit_routes',
        'visit_surveys',
        'voice_reports',
        'vulnerability_scans',
        'warehouses',
        'warranty_tracking',
        'webhook_configs',
        'webhooks',
        'weight_assignments',
        'whatsapp_configs',
        'work_order_chats',
        'work_order_checklist_responses',
        'work_order_displacement_locations',
        'work_order_displacement_stops',
        'work_order_events',
        'work_order_ratings',
        'work_order_recurrences',
        'work_order_signatures',
        'work_order_templates',
        'work_order_time_logs',
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
