<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rescissions', function (Blueprint $table) {
            if (! Schema::hasColumn('rescissions', 'advance_deductions')) {
                $table->decimal('advance_deductions', 12, 2)->default(0)->after('fgts_penalty_rate');
            }
            if (! Schema::hasColumn('rescissions', 'hour_bank_payout')) {
                $table->decimal('hour_bank_payout', 12, 2)->default(0)->after('advance_deductions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rescissions', function (Blueprint $table) {
            if (Schema::hasColumn('rescissions', 'advance_deductions')) {
                $table->dropColumn('advance_deductions');
            }
            if (Schema::hasColumn('rescissions', 'hour_bank_payout')) {
                $table->dropColumn('hour_bank_payout');
            }
        });
    }
};
