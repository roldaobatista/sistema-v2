<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('non_conformities')) {
            Schema::create('non_conformities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('nc_number')->unique();
                $table->string('title');
                $table->text('description');
                $table->string('source'); // audit, customer_complaint, process_deviation
                $table->string('severity'); // minor, major, critical
                $table->string('status')->default('open'); // open, investigating, corrective_action, closed
                $table->foreignId('reported_by')->constrained('users');
                $table->foreignId('assigned_to')->nullable()->constrained('users');
                $table->date('due_date')->nullable();
                $table->datetime('closed_at')->nullable();
                $table->text('root_cause')->nullable();
                $table->text('corrective_action')->nullable();
                $table->text('preventive_action')->nullable();
                $table->text('verification_notes')->nullable();
                $table->foreignId('capa_record_id')->nullable()->constrained('capa_records')->nullOnDelete();
                $table->foreignId('quality_audit_id')->nullable()->constrained('quality_audits')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('non_conformities');
    }
};
