<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Análise Crítica do Contrato (ISO 17025 / Normativo Calibração)
            $table->string('service_modality', 30)->nullable()->after('service_type')
                ->comment('calibration, inspection, maintenance, adjustment, diagnostic');
            $table->boolean('requires_adjustment')->default(false)->after('service_modality')
                ->comment('Haverá ajuste antes da calibração?');
            $table->boolean('requires_maintenance')->default(false)->after('requires_adjustment')
                ->comment('Haverá manutenção/reparo?');
            $table->boolean('client_wants_conformity_declaration')->default(false)->after('requires_maintenance')
                ->comment('Cliente quer declaração de conformidade?');
            $table->string('decision_rule_agreed', 30)->nullable()->after('client_wants_conformity_declaration')
                ->comment('simple, guard_band, shared_risk — regra de decisão acordada');
            $table->boolean('subject_to_legal_metrology')->default(false)->after('decision_rule_agreed')
                ->comment('Instrumento sujeito à metrologia legal?');
            $table->boolean('needs_ipem_interaction')->default(false)->after('subject_to_legal_metrology')
                ->comment('Necessita interação com IPEM?');
            $table->text('site_conditions')->nullable()->after('needs_ipem_interaction')
                ->comment('Condições especiais do local que possam afetar resultado');
            $table->text('calibration_scope_notes')->nullable()->after('site_conditions')
                ->comment('Observações sobre escopo da calibração contratada');
            $table->boolean('will_emit_complementary_report')->default(false)->after('calibration_scope_notes')
                ->comment('Emissão de laudo técnico complementar além do certificado?');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn([
                'service_modality',
                'requires_adjustment',
                'requires_maintenance',
                'client_wants_conformity_declaration',
                'decision_rule_agreed',
                'subject_to_legal_metrology',
                'needs_ipem_interaction',
                'site_conditions',
                'calibration_scope_notes',
                'will_emit_complementary_report',
            ]);
        });
    }
};
