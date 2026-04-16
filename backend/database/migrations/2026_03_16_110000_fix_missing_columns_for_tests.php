<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration corretiva para alinhar colunas esperadas mas não existentes
 * que causam falhas nos testes em SQLite.
 *
 * Correções:
 * 1. fiscal_notes.deleted_at (Model usa SoftDeletes, migration não tinha)
 * 2. debt_renegotiation_items.tenant_id (Missing column para FK e multi-tenancy)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Fiscal Notes - SoftDeletes
        if (Schema::hasTable('fiscal_notes')) {
            Schema::table('fiscal_notes', function (Blueprint $table) {
                if (! Schema::hasColumn('fiscal_notes', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // 2. Debt Renegotiation Items - tenant_id
        if (Schema::hasTable('debt_renegotiation_items')) {
            Schema::table('debt_renegotiation_items', function (Blueprint $table) {
                if (! Schema::hasColumn('debt_renegotiation_items', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->constrained()->onUpdate('cascade')->onDelete('cascade');
                }
            });
        }

        // 2.5. Expense Status History - tenant_id
        if (Schema::hasTable('expense_status_history')) {
            Schema::table('expense_status_history', function (Blueprint $table) {
                if (! Schema::hasColumn('expense_status_history', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->constrained()->onUpdate('cascade')->onDelete('cascade');
                }
            });
        }

        // 3. SQLite "no such column: received_at" in em_tenant_folder_received index
        // The index was created on 'emails' table but the column is 'date', not 'received_at'.
        // This causes SQLite to fail any subsequent table alter commands.
        if (Schema::hasTable('emails')) {
            Schema::table('emails', function (Blueprint $table) {
                if (Schema::hasIndex('emails', 'em_tenant_folder_received')) {
                    $table->dropIndex('em_tenant_folder_received');
                }

                // Only create the correct index if we have the 'date' column
                if (Schema::hasColumn('emails', 'date') && ! Schema::hasIndex('emails', 'em_tenant_folder_date')) {
                    $table->index(['tenant_id', 'folder', 'date'], 'em_tenant_folder_date');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fiscal_notes') && Schema::hasColumn('fiscal_notes', 'deleted_at')) {
            Schema::table('fiscal_notes', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('debt_renegotiation_items') && Schema::hasColumn('debt_renegotiation_items', 'tenant_id')) {
            // Em MySQL podemos dar dropColumn. Em SQLite, o Gramar custom já contorna FK.
            Schema::table('debt_renegotiation_items', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
