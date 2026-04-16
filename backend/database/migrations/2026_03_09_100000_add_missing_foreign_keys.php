<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FK: accounts_receivable.invoice_id → invoices.id
        if (Schema::hasTable('accounts_receivable') && Schema::hasColumn('accounts_receivable', 'invoice_id')) {
            Schema::table('accounts_receivable', function (Blueprint $table) {
                $table->foreign('invoice_id')
                    ->references('id')
                    ->on('invoices')
                    ->nullOnDelete();
            });
        }

        // FK: work_order_templates.checklist_id → service_checklists.id
        if (Schema::hasTable('work_order_templates') && Schema::hasColumn('work_order_templates', 'checklist_id')) {
            Schema::table('work_order_templates', function (Blueprint $table) {
                $table->foreign('checklist_id')
                    ->references('id')
                    ->on('service_checklists')
                    ->nullOnDelete();
            });
        }

        // FK: work_order_templates.created_by → users.id
        if (Schema::hasTable('work_order_templates') && Schema::hasColumn('work_order_templates', 'created_by')) {
            Schema::table('work_order_templates', function (Blueprint $table) {
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('accounts_receivable') && Schema::hasColumn('accounts_receivable', 'invoice_id')) {
            Schema::table('accounts_receivable', function (Blueprint $table) {
                $table->dropForeign(['invoice_id']);
            });
        }

        if (Schema::hasTable('work_order_templates')) {
            Schema::table('work_order_templates', function (Blueprint $table) {
                if (Schema::hasColumn('work_order_templates', 'checklist_id')) {
                    $table->dropForeign(['checklist_id']);
                }
                if (Schema::hasColumn('work_order_templates', 'created_by')) {
                    $table->dropForeign(['created_by']);
                }
            });
        }
    }
};
