<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_emission_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('equipment_calibration_id')->constrained('equipment_calibrations')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('verified_by')->constrained('users')->onUpdate('cascade')->onDelete('cascade');

            // 11 itens obrigatórios do checklist normativo
            $table->boolean('equipment_identified')->default(false)
                ->comment('Balança identificada sem ambiguidade (marca, modelo, nº série, capacidade, divisão)');
            $table->boolean('scope_defined')->default(false)
                ->comment('OS define claramente o escopo');
            $table->boolean('critical_analysis_done')->default(false)
                ->comment('Análise crítica do pedido/contrato realizada');
            $table->boolean('procedure_defined')->default(false)
                ->comment('Procedimento técnico aplicável definido');
            $table->boolean('standards_traceable')->default(false)
                ->comment('Padrões usados têm rastreabilidade documentada');
            $table->boolean('raw_data_recorded')->default(false)
                ->comment('Dados brutos registrados');
            $table->boolean('uncertainty_calculated')->default(false)
                ->comment('Incerteza determinada e lançada');
            $table->boolean('adjustment_documented')->default(false)
                ->comment('Claro se houve ajuste/manutenção');
            $table->boolean('no_undue_interval')->default(false)
                ->comment('Sem validade/recomendação de intervalo indevida');
            $table->boolean('conformity_declaration_valid')->default(false)
                ->comment('Se declaração de conformidade: regra definida antes + resultados + incerteza presentes');
            $table->boolean('accreditation_mark_correct')->default(false)
                ->comment('Uso de marca/símbolo de acreditação correto (ou ausente, se não acreditado)');

            $table->text('observations')->nullable();
            $table->boolean('approved')->default(false)->comment('Todos os itens verificados e aprovados?');
            $table->datetime('verified_at')->nullable();
            $table->timestamps();

            $table->unique('equipment_calibration_id', 'cert_checklist_cal_unique');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_emission_checklists');
    }
};
