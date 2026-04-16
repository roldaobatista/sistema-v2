<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('reference_month', 7); // YYYY-MM
            $table->string('type', 30)->default('regular'); // regular, thirteenth_first, thirteenth_second, vacation, rescission, advance
            $table->string('status', 20)->default('draft'); // draft, calculated, approved, paid, cancelled
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_fgts', 14, 2)->default(0);
            $table->decimal('total_inss_employer', 14, 2)->default(0);
            $table->integer('employee_count')->default(0);
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference_month', 'type']);
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('overtime_50_hours', 8, 2)->default(0);
            $table->decimal('overtime_50_value', 12, 2)->default(0);
            $table->decimal('overtime_100_hours', 8, 2)->default(0);
            $table->decimal('overtime_100_value', 12, 2)->default(0);
            $table->decimal('night_hours', 8, 2)->default(0);
            $table->decimal('night_shift_value', 12, 2)->default(0);
            $table->decimal('dsr_value', 12, 2)->default(0);
            $table->decimal('commission_value', 12, 2)->default(0);
            $table->decimal('bonus_value', 12, 2)->default(0);
            $table->decimal('other_earnings', 12, 2)->default(0);
            $table->decimal('inss_employee', 12, 2)->default(0);
            $table->decimal('irrf', 12, 2)->default(0);
            $table->decimal('transportation_discount', 12, 2)->default(0);
            $table->decimal('meal_discount', 12, 2)->default(0);
            $table->decimal('health_insurance_discount', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('advance_discount', 12, 2)->default(0);
            $table->decimal('fgts_value', 12, 2)->default(0);
            $table->decimal('inss_employer_value', 12, 2)->default(0);
            $table->integer('worked_days')->default(0);
            $table->integer('absence_days')->default(0);
            $table->decimal('absence_value', 12, 2)->default(0);
            $table->integer('vacation_days')->default(0);
            $table->decimal('vacation_value', 12, 2)->default(0);
            $table->decimal('vacation_bonus', 12, 2)->default(0);
            $table->decimal('thirteenth_value', 12, 2)->default(0);
            $table->integer('thirteenth_months')->default(0);
            $table->string('status', 20)->default('calculated'); // calculated, reviewed, approved
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payroll_id', 'user_id']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_line_id')->constrained('payroll_lines')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('reference_month', 7);
            $table->string('file_path', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->string('digital_signature_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'reference_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payrolls');
    }
};
