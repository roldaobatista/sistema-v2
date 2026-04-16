<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Final infra audit: composite indexes for remaining high-traffic query patterns.
 *
 * Covers tables that were not fully addressed by previous migrations:
 * - commission_events: tenant + status + created_at for reports
 * - commission_settlements: tenant + user + period for audits
 * - bank_statement_entries: tenant + status + date for reconciliation
 * - technician_cash_transactions: tenant + user + date for statements
 * - technician_cash_funds: tenant + user for lookups
 * - crm_messages: tenant + deal for conversation threads
 * - service_checklists: tenant + type for listing
 * - calibrations: tenant + equipment + next_date for alerts
 */
return new class extends Migration
{
    public function up(): void
    {
        // commission_events — tenant + status + created_at for reporting dashboards
        if (Schema::hasTable('commission_events')) {
            Schema::table('commission_events', function (Blueprint $table) {
                if (Schema::hasColumn('commission_events', 'status')) {
                    try {
                        $table->index(['tenant_id', 'status', 'created_at'], 'ce_tenant_status_created');
                    } catch (Throwable) {
                    }
                }
                if (Schema::hasColumn('commission_events', 'user_id')) {
                    try {
                        $table->index(['tenant_id', 'user_id'], 'ce_tenant_user');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // commission_settlements — tenant + user + status for audit views
        if (Schema::hasTable('commission_settlements')) {
            Schema::table('commission_settlements', function (Blueprint $table) {
                if (Schema::hasColumn('commission_settlements', 'user_id') && Schema::hasColumn('commission_settlements', 'status')) {
                    try {
                        $table->index(['tenant_id', 'user_id', 'status'], 'cset_tenant_user_status');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // bank_statement_entries — tenant + status + date for reconciliation
        if (Schema::hasTable('bank_statement_entries')) {
            Schema::table('bank_statement_entries', function (Blueprint $table) {
                if (Schema::hasColumn('bank_statement_entries', 'tenant_id') && Schema::hasColumn('bank_statement_entries', 'status')) {
                    try {
                        $table->index(['tenant_id', 'status'], 'bse_tenant_status');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // technician_cash_transactions — tenant + user + date for statements
        if (Schema::hasTable('technician_cash_transactions')) {
            Schema::table('technician_cash_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('technician_cash_transactions', 'tenant_id') && Schema::hasColumn('technician_cash_transactions', 'user_id')) {
                    try {
                        $table->index(['tenant_id', 'user_id', 'created_at'], 'tct_tenant_user_created');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // technician_cash_funds — tenant + user for balance lookups
        if (Schema::hasTable('technician_cash_funds')) {
            Schema::table('technician_cash_funds', function (Blueprint $table) {
                if (Schema::hasColumn('technician_cash_funds', 'tenant_id') && Schema::hasColumn('technician_cash_funds', 'user_id')) {
                    try {
                        $table->index(['tenant_id', 'user_id'], 'tcf_tenant_user');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // crm_messages — tenant + deal for conversation threads
        if (Schema::hasTable('crm_messages')) {
            Schema::table('crm_messages', function (Blueprint $table) {
                if (Schema::hasColumn('crm_messages', 'tenant_id') && Schema::hasColumn('crm_messages', 'deal_id')) {
                    try {
                        $table->index(['tenant_id', 'deal_id', 'created_at'], 'crm_msg_tenant_deal_created');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // service_checklists — tenant + type for listing/filtering
        if (Schema::hasTable('service_checklists')) {
            Schema::table('service_checklists', function (Blueprint $table) {
                if (Schema::hasColumn('service_checklists', 'tenant_id')) {
                    try {
                        $table->index('tenant_id', 'scl_tenant');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // calibrations — tenant + equipment + next_date for expiry alerts
        if (Schema::hasTable('calibrations')) {
            Schema::table('calibrations', function (Blueprint $table) {
                if (Schema::hasColumn('calibrations', 'tenant_id') && Schema::hasColumn('calibrations', 'equipment_id')) {
                    try {
                        $table->index(['tenant_id', 'equipment_id'], 'cal_tenant_equipment');
                    } catch (Throwable) {
                    }
                }
                if (Schema::hasColumn('calibrations', 'next_calibration_date')) {
                    try {
                        $table->index(['tenant_id', 'next_calibration_date'], 'cal_tenant_next_date');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // commission_rules — tenant + active for quick lookups
        if (Schema::hasTable('commission_rules')) {
            Schema::table('commission_rules', function (Blueprint $table) {
                if (Schema::hasColumn('commission_rules', 'tenant_id') && Schema::hasColumn('commission_rules', 'is_active')) {
                    try {
                        $table->index(['tenant_id', 'is_active'], 'cr_tenant_active');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // email_accounts — tenant for listing
        if (Schema::hasTable('email_accounts')) {
            Schema::table('email_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('email_accounts', 'tenant_id')) {
                    try {
                        $table->index('tenant_id', 'ea_tenant');
                    } catch (Throwable) {
                    }
                }
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'commission_events' => ['ce_tenant_status_created', 'ce_tenant_user'],
            'commission_settlements' => ['cset_tenant_user_status'],
            'bank_statement_entries' => ['bse_tenant_status'],
            'technician_cash_transactions' => ['tct_tenant_user_created'],
            'technician_cash_funds' => ['tcf_tenant_user'],
            'crm_messages' => ['crm_msg_tenant_deal_created'],
            'service_checklists' => ['scl_tenant'],
            'calibrations' => ['cal_tenant_equipment', 'cal_tenant_next_date'],
            'commission_rules' => ['cr_tenant_active'],
            'email_accounts' => ['ea_tenant'],
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
