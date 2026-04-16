<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infra audit: composite indexes for (tenant_id, status) on high-traffic tables.
 *
 * Most listing pages filter by tenant_id + status. A composite index
 * eliminates the need for MySQL to intersect two separate indexes.
 * Only covers tables where status is frequently used in WHERE clauses.
 */
return new class extends Migration
{
    private array $tables = [
        'contracts',
        'corrective_actions',
        'debt_renegotiations',
        'fleet_vehicles',
        'follow_ups',
        'inventories',
        'inventory_counts',
        'invoices',
        'job_postings',
        'material_requests',
        'non_conformances',
        'onboarding_processes',
        'performance_reviews',
        'purchase_quotations',
        'purchase_quotes',
        'qa_alerts',
        'quality_audits',
        'quality_procedures',
        'recall_logs',
        'rma_requests',
        'schedules',
        'stock_disposals',
        'stock_transfers',
        'tech_cash_advances',
        'tool_inventories',
        'traffic_fines',
        'training_enrollments',
        'trainings',
        'vehicle_inspections',
        'virtual_cards',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'tenant_id') || ! Schema::hasColumn($table, 'status')) {
                continue;
            }

            $indexName = substr($table, 0, 45).'_tid_st_idx';

            Schema::table($table, function (Blueprint $t) use ($indexName) {
                try {
                    $t->index(['tenant_id', 'status'], $indexName);
                } catch (Throwable) {
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

            $indexName = substr($table, 0, 45).'_tid_st_idx';

            Schema::table($table, function (Blueprint $t) use ($indexName) {
                try {
                    $t->dropIndex($indexName);
                } catch (Throwable) {
                }
            });
        }
    }
};
