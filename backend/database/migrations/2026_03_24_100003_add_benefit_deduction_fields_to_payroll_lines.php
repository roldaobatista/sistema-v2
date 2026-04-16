<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_lines', 'advance_deduction')) {
                $table->decimal('advance_deduction', 10, 2)->default(0)->after('advance_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_lines', 'advance_deduction')) {
                $table->dropColumn('advance_deduction');
            }
        });
    }
};
