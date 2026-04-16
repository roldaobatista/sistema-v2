<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditation_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('accreditation_number', 100);
            $table->string('accrediting_body', 100)->default('Cgcre/Inmetro');
            $table->text('scope_description');
            $table->json('equipment_categories');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('certificate_file', 500)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('equipment_calibrations', function (Blueprint $table) {
            $table->foreign('accreditation_scope_id')
                ->references('id')
                ->on('accreditation_scopes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_calibrations', function (Blueprint $table) {
            $table->dropForeign(['accreditation_scope_id']);
        });

        Schema::dropIfExists('accreditation_scopes');
    }
};
