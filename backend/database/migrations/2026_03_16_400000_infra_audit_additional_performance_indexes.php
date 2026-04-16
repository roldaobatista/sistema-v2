<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional performance indexes discovered during infra audit round 2.
 *
 * Covers tables not addressed by previous audit migrations:
 * - collection_actions: status + scheduled_at for scheduled collection processing
 * - collection_logs: account_receivable_id + status for collection history
 * - debt_renegotiation_items: account_receivable_id for renegotiation lookups
 * - commission_splits: commission_event_id for split calculations
 * - commission_events: work_order_id for per-OS commission reports
 * - corrective_actions: polymorphic sourceable for timeline
 * - contract_measurements: contract_id for measurement history
 * - fiscal_scheduled_emissions: customer_id + status for dashboard
 * - financial_checks: status + due_date for overdue alerts
 * - api_keys: tenant_id + is_active for token validation
 * - crm_deal_competitors: deal_id for competitor timeline
 */
return new class extends Migration
{
    public function up(): void
    {
        // collection_actions — status + scheduled_at for processing scheduled collections
        if (Schema::hasTable('collection_actions')) {
            Schema::table('collection_actions', function (Blueprint $table) {
                if (Schema::hasColumn('collection_actions', 'tenant_id') && Schema::hasColumn('collection_actions', 'status')) {
                    try {
                        $table->index(['tenant_id', 'status', 'scheduled_at'], 'ca_tenant_status_scheduled');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // collection_logs — AR + status for collection history dashboard
        if (Schema::hasTable('collection_logs')) {
            Schema::table('collection_logs', function (Blueprint $table) {
                if (Schema::hasColumn('collection_logs', 'account_receivable_id')) {
                    try {
                        $table->index(['account_receivable_id', 'status'], 'cl_ar_status');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // debt_renegotiation_items — FK account_receivable_id
        if (Schema::hasTable('debt_renegotiation_items')) {
            Schema::table('debt_renegotiation_items', function (Blueprint $table) {
                if (Schema::hasColumn('debt_renegotiation_items', 'account_receivable_id')) {
                    try {
                        $table->index('account_receivable_id', 'dri_ar');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // commission_splits — FK commission_event_id
        if (Schema::hasTable('commission_splits')) {
            Schema::table('commission_splits', function (Blueprint $table) {
                if (Schema::hasColumn('commission_splits', 'commission_event_id')) {
                    try {
                        $table->index('commission_event_id', 'cs_event');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // commission_events — work_order_id for per-OS commission calc
        if (Schema::hasTable('commission_events')) {
            Schema::table('commission_events', function (Blueprint $table) {
                if (Schema::hasColumn('commission_events', 'work_order_id')) {
                    try {
                        $table->index(['work_order_id', 'status'], 'ce_wo_status');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // corrective_actions — polymorphic sourceable
        if (Schema::hasTable('corrective_actions')) {
            Schema::table('corrective_actions', function (Blueprint $table) {
                if (Schema::hasColumn('corrective_actions', 'sourceable_type') && Schema::hasColumn('corrective_actions', 'sourceable_id')) {
                    try {
                        $table->index(['sourceable_type', 'sourceable_id'], 'corract_sourceable_poly');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // contract_measurements — FK contract_id
        if (Schema::hasTable('contract_measurements')) {
            Schema::table('contract_measurements', function (Blueprint $table) {
                if (Schema::hasColumn('contract_measurements', 'contract_id')) {
                    try {
                        $table->index('contract_id', 'cm_contract');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // fiscal_scheduled_emissions — customer + status
        if (Schema::hasTable('fiscal_scheduled_emissions')) {
            Schema::table('fiscal_scheduled_emissions', function (Blueprint $table) {
                if (Schema::hasColumn('fiscal_scheduled_emissions', 'customer_id') && Schema::hasColumn('fiscal_scheduled_emissions', 'status')) {
                    try {
                        $table->index(['tenant_id', 'customer_id', 'status'], 'fse_tenant_customer_status');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // financial_checks — status + due_date for overdue alerts
        if (Schema::hasTable('financial_checks')) {
            Schema::table('financial_checks', function (Blueprint $table) {
                if (Schema::hasColumn('financial_checks', 'status') && Schema::hasColumn('financial_checks', 'due_date')) {
                    try {
                        $table->index(['tenant_id', 'status', 'due_date'], 'fc_tenant_status_due');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // api_keys — tenant + is_active for token validation
        if (Schema::hasTable('api_keys')) {
            Schema::table('api_keys', function (Blueprint $table) {
                if (Schema::hasColumn('api_keys', 'tenant_id') && Schema::hasColumn('api_keys', 'is_active')) {
                    try {
                        $table->index(['tenant_id', 'is_active'], 'ak_tenant_active');
                    } catch (Throwable) {
                    }
                }
                if (Schema::hasColumn('api_keys', 'created_by')) {
                    try {
                        $table->index('created_by', 'ak_created_by');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // crm_deal_competitors — FK deal_id
        if (Schema::hasTable('crm_deal_competitors')) {
            Schema::table('crm_deal_competitors', function (Blueprint $table) {
                if (Schema::hasColumn('crm_deal_competitors', 'deal_id')) {
                    try {
                        $table->index('deal_id', 'cdc_deal');
                    } catch (Throwable) {
                    }
                }
            });
        }

        // expenses — reviewed_by for audit reports
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (Schema::hasColumn('expenses', 'reviewed_by')) {
                    try {
                        $table->index('reviewed_by', 'exp_reviewed_by');
                    } catch (Throwable) {
                    }
                }
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'collection_actions' => ['ca_tenant_status_scheduled'],
            'collection_logs' => ['cl_ar_status'],
            'debt_renegotiation_items' => ['dri_ar'],
            'commission_splits' => ['cs_event'],
            'commission_events' => ['ce_wo_status'],
            'corrective_actions' => ['corract_sourceable_poly'],
            'contract_measurements' => ['cm_contract'],
            'fiscal_scheduled_emissions' => ['fse_tenant_customer_status'],
            'financial_checks' => ['fc_tenant_status_due'],
            'api_keys' => ['ak_tenant_active', 'ak_created_by'],
            'crm_deal_competitors' => ['cdc_deal'],
            'expenses' => ['exp_reviewed_by'],
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
