<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste o resultado do cálculo da regra de decisão
 * (ISO/IEC 17025:2017 §7.8.6.2 exige registro do que foi aplicado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_calibrations', function (Blueprint $table) {
            $table->string('decision_result', 10)->nullable()->after('consumer_risk_beta')
                ->comment('accept | warn | reject (computed by ConformityAssessmentService)');
            $table->decimal('decision_z_value', 10, 4)->nullable()->after('decision_result')
                ->comment('z = (|err|-EMA)/u_c for shared_risk (u_c = U/k)');
            $table->decimal('decision_false_accept_prob', 8, 6)->nullable()->after('decision_z_value')
                ->comment('P_fa or P_fr from normal CDF (shared_risk)');
            $table->decimal('decision_guard_band_applied', 12, 6)->nullable()->after('decision_false_accept_prob')
                ->comment('w effectively applied (guard_band mode)');
            $table->dateTime('decision_calculated_at')->nullable()->after('decision_guard_band_applied');
            $table->unsignedBigInteger('decision_calculated_by')->nullable()->after('decision_calculated_at');
            $table->text('decision_notes')->nullable()->after('decision_calculated_by');

            $table->foreign('decision_calculated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'decision_result'], 'eq_cal_decision_result_idx');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_calibrations', function (Blueprint $table) {
            $table->dropIndex('eq_cal_decision_result_idx');
            $table->dropForeign(['decision_calculated_by']);
            $table->dropColumn([
                'decision_result',
                'decision_z_value',
                'decision_false_accept_prob',
                'decision_guard_band_applied',
                'decision_calculated_at',
                'decision_calculated_by',
                'decision_notes',
            ]);
        });
    }
};
