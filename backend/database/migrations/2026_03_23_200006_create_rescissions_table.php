<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rescissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30); // sem_justa_causa, justa_causa, pedido_demissao, acordo_mutuo, termino_contrato
            $table->date('notice_date')->nullable();
            $table->date('termination_date');
            $table->date('last_work_day')->nullable();
            $table->string('notice_type', 20)->nullable(); // worked, indemnified, waived
            $table->integer('notice_days')->default(30);
            $table->decimal('notice_value', 12, 2)->default(0);
            $table->integer('salary_balance_days')->default(0);
            $table->decimal('salary_balance_value', 12, 2)->default(0);
            $table->integer('vacation_proportional_days')->default(0);
            $table->decimal('vacation_proportional_value', 12, 2)->default(0);
            $table->decimal('vacation_bonus_value', 12, 2)->default(0); // 1/3 constitucional sobre férias proporcionais
            $table->integer('vacation_overdue_days')->default(0);
            $table->decimal('vacation_overdue_value', 12, 2)->default(0);
            $table->decimal('vacation_overdue_bonus_value', 12, 2)->default(0); // 1/3 sobre férias vencidas
            $table->integer('thirteenth_proportional_months')->default(0);
            $table->decimal('thirteenth_proportional_value', 12, 2)->default(0);
            $table->decimal('fgts_balance', 12, 2)->default(0);
            $table->decimal('fgts_penalty_value', 12, 2)->default(0); // multa 40% ou 20%
            $table->decimal('fgts_penalty_rate', 5, 2)->default(40); // 40% normal, 20% acordo mútuo
            $table->decimal('other_earnings', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('inss_deduction', 12, 2)->default(0);
            $table->decimal('irrf_deduction', 12, 2)->default(0);
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, calculated, approved, paid, cancelled
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('trct_file_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rescissions');
    }
};
