<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('payroll_id')->nullable()->after('reimbursement_ap_id')->constrained('payrolls')->nullOnDelete();
            $table->foreignId('payroll_line_id')->nullable()->after('payroll_id')->constrained('payroll_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_line_id');
            $table->dropConstrainedForeignId('payroll_id');
        });
    }
};
