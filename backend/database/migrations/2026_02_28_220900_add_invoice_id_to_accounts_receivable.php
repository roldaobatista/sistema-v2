<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts_receivable', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('work_order_id')
                    ->comment('Fatura que gerou este título — vínculo direto para reconciliação');
                $table->index('invoice_id', 'ar_invoice_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            if (Schema::hasColumn('accounts_receivable', 'invoice_id')) {
                $table->dropIndex('ar_invoice_id_idx');
                $table->dropColumn('invoice_id');
            }
        });
    }
};
