<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log de auditoria dos cálculos de regra de decisão.
 * Cada reavaliação cria um registro com inputs/outputs/engine_version.
 * Serve para rastreabilidade ISO/IEC 17025 §8.5 (registro de decisões).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_decision_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_calibration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('decision_rule', 20);
            $table->json('inputs');
            $table->json('outputs');
            $table->string('engine_version', 20);
            $table->timestamps();

            $table->index(['tenant_id', 'equipment_calibration_id'], 'cal_decision_logs_tenant_cal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_decision_logs');
    }
};
