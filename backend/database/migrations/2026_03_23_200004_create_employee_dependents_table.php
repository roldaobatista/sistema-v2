<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->string('cpf', 11)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('relationship', 30); // filho, conjuge, pais, outro
            $table->boolean('is_irrf_dependent')->default(true);
            $table->boolean('is_benefit_dependent')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_dependents');
    }
};
