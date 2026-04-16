<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add quote_id to accounts_receivable for direct financial integration
        if (Schema::hasTable('accounts_receivable') && ! Schema::hasColumn('accounts_receivable', 'quote_id')) {
            Schema::table('accounts_receivable', function (Blueprint $table) {
                $table->unsignedBigInteger('quote_id')->nullable()->after('work_order_id');
                $table->string('origin_type', 30)->nullable()->after('quote_id');

                $table->index('quote_id', 'ar_quote_id_idx');
                $table->index('origin_type', 'ar_origin_type_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('accounts_receivable') && Schema::hasColumn('accounts_receivable', 'quote_id')) {
            Schema::table('accounts_receivable', function (Blueprint $table) {
                $table->dropIndex('ar_quote_id_idx');
                $table->dropIndex('ar_origin_type_idx');
                $table->dropColumn(['quote_id', 'origin_type']);
            });
        }
    }
};
