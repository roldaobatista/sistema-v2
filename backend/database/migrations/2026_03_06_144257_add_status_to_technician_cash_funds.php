<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('technician_cash_funds', 'status')) {
            Schema::table('technician_cash_funds', function (Blueprint $table) {
                $table->string('status', 30)->default('active')->after('card_balance');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('technician_cash_funds', 'status')) {
            Schema::table('technician_cash_funds', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
