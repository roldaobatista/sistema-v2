<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linearity_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_calibration_id')->constrained()->cascadeOnDelete();

            $table->integer('point_order');
            $table->decimal('reference_value', 12, 4);
            $table->string('unit', 20)->default('g');

            $table->decimal('indication_increasing', 12, 4)->nullable();
            $table->decimal('indication_decreasing', 12, 4)->nullable();

            $table->decimal('error_increasing', 12, 4)->nullable();
            $table->decimal('error_decreasing', 12, 4)->nullable();
            $table->decimal('hysteresis', 12, 4)->nullable();
            $table->decimal('max_permissible_error', 12, 4)->nullable();
            $table->boolean('conforms')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'equipment_calibration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linearity_tests');
    }
};
