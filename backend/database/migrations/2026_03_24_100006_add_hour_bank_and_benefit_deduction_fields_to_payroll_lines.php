<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_lines', 'hour_bank_payout_hours')) {
                $table->decimal('hour_bank_payout_hours', 8, 2)->default(0)->after('absence_value');
            }
            if (! Schema::hasColumn('payroll_lines', 'hour_bank_payout_value')) {
                $table->decimal('hour_bank_payout_value', 10, 2)->default(0)->after('hour_bank_payout_hours');
            }
            if (! Schema::hasColumn('payroll_lines', 'vt_deduction')) {
                $table->decimal('vt_deduction', 10, 2)->default(0)->after('hour_bank_payout_value');
            }
            if (! Schema::hasColumn('payroll_lines', 'vr_deduction')) {
                $table->decimal('vr_deduction', 10, 2)->default(0)->after('vt_deduction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropColumn(['hour_bank_payout_hours', 'hour_bank_payout_value', 'vt_deduction', 'vr_deduction']);
        });
    }
};
