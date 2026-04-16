<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('technician_cash_funds', function (Blueprint $table) {
            $table->decimal('credit_limit', 10, 2)->nullable()->after('card_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technician_cash_funds', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });
    }
};
