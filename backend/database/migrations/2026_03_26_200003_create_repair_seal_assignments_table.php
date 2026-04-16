<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_seal_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seal_id')->constrained('inmetro_seals')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('action', 20)->comment('assigned, returned, transferred');
            $table->foreignId('previous_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'seal_id']);
            $table->index(['tenant_id', 'technician_id', 'created_at'], 'idx_assignments_tech_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_seal_assignments');
    }
};
