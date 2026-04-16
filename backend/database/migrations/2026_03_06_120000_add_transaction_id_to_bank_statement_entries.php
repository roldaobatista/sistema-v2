<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_statement_entries') && ! Schema::hasColumn('bank_statement_entries', 'transaction_id')) {
            Schema::table('bank_statement_entries', function (Blueprint $table) {
                $table->string('transaction_id', 100)->nullable();
                $table->index('transaction_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_statement_entries') && Schema::hasColumn('bank_statement_entries', 'transaction_id')) {
            Schema::table('bank_statement_entries', function (Blueprint $table) {
                $table->dropIndex(['transaction_id']);
                $table->dropColumn('transaction_id');
            });
        }
    }
};
