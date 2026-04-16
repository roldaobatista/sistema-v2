<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esocial_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('description');
            $table->string('nature', 20); // provento, desconto, informativa
            $table->string('type', 30); // salario_base, he_50, he_100, noturno, dsr, inss, irrf, fgts, vt, vr, va, other
            $table->boolean('incidence_inss')->default(false);
            $table->boolean('incidence_irrf')->default(false);
            $table->boolean('incidence_fgts')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esocial_rubrics');
    }
};
