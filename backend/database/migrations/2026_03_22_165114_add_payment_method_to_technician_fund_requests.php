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
        Schema::table('technician_fund_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('technician_fund_requests', 'payment_method')) {
                $table->string('payment_method', 30)->nullable()->after('amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technician_fund_requests', function (Blueprint $table) {
            if (Schema::hasColumn('technician_fund_requests', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
