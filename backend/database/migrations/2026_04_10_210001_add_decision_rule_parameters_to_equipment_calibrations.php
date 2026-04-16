<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona parâmetros de regra de decisão (ILAC G8:09/2019, P14:09/2020, JCGM 106:2012)
 * em equipment_calibrations:
 *  - coverage_factor_k, confidence_level (ILAC P14:09/2020)
 *  - guard_band_mode, guard_band_value (ILAC G8 §4.2.2)
 *  - producer_risk_alpha, consumer_risk_beta (ILAC G8 §4.2.3, JCGM 106 §9)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_calibrations', function (Blueprint $table) {
            // Parâmetros de cobertura (ILAC P14:09/2020)
            $table->decimal('coverage_factor_k', 5, 2)->nullable()->after('uncertainty_budget')
                ->comment('Coverage factor k (typically 2.00 for ~95.45%)');
            $table->decimal('confidence_level', 5, 2)->nullable()->after('coverage_factor_k')
                ->comment('Confidence level percent (e.g. 95.45)');

            // Parâmetros guard_band (ILAC G8 §4.2.2)
            $table->string('guard_band_mode', 20)->nullable()->after('confidence_level')
                ->comment('k_times_u | percent_limit | fixed_abs');
            $table->decimal('guard_band_value', 12, 6)->nullable()->after('guard_band_mode')
                ->comment('Numeric value: k multiplier, percent of limit, or absolute');

            // Parâmetros shared_risk (ILAC G8 §4.2.3 / JCGM 106 §9)
            $table->decimal('producer_risk_alpha', 6, 4)->nullable()->after('guard_band_value')
                ->comment('Max false reject probability (e.g. 0.0500)');
            $table->decimal('consumer_risk_beta', 6, 4)->nullable()->after('producer_risk_alpha')
                ->comment('Max false accept probability (e.g. 0.0500)');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_calibrations', function (Blueprint $table) {
            $table->dropColumn([
                'coverage_factor_k',
                'confidence_level',
                'guard_band_mode',
                'guard_band_value',
                'producer_risk_alpha',
                'consumer_risk_beta',
            ]);
        });
    }
};
