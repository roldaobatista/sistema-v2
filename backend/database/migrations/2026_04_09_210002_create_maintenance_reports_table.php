<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('work_order_id')->constrained('work_orders')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('equipment_id')->constrained('equipments')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('performed_by')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');

            // Diagnóstico
            $table->text('defect_found')->comment('Defeito encontrado');
            $table->text('probable_cause')->nullable()->comment('Causa provável');
            $table->text('corrective_action')->nullable()->comment('Ação corretiva realizada');

            // Peças e componentes
            $table->json('parts_replaced')->nullable()->comment('Peças trocadas: [{name, part_number, origin, quantity}]');
            $table->string('seal_status', 30)->nullable()->comment('intact, broken, replaced, not_applicable');
            $table->string('new_seal_number', 50)->nullable()->comment('Número do novo lacre/selo, se aplicável');

            // Condição
            $table->string('condition_before', 30)->default('defective')
                ->comment('Condição antes: defective, degraded, functional, unknown');
            $table->string('condition_after', 30)->default('functional')
                ->comment('Condição depois: functional, limited, requires_calibration, not_repaired');
            $table->boolean('requires_calibration_after')->default(true)
                ->comment('Necessita calibração após intervenção?');
            $table->boolean('requires_ipem_verification')->default(false)
                ->comment('Necessita verificação pelo IPEM após reparo?');

            // Observações e evidências
            $table->text('notes')->nullable();
            $table->json('photo_evidence')->nullable()->comment('URLs das fotos de evidência');

            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'work_order_id']);
            $table->index(['tenant_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_reports');
    }
};
