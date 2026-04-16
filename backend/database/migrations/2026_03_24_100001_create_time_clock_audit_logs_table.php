<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_clock_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('time_clock_entry_id')->nullable();
            $table->unsignedBigInteger('time_clock_adjustment_id')->nullable();
            $table->string('action'); // created, approved, rejected, clock_out, confirmed, adjustment_requested, adjustment_approved, adjustment_rejected, integrity_verified, exported, tampering_attempt
            $table->foreignId('performed_by')->constrained('users');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'time_clock_entry_id']);
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'created_at']);

            $table->foreign('time_clock_entry_id')->references('id')->on('time_clock_entries')->nullOnDelete();
            $table->foreign('time_clock_adjustment_id')->references('id')->on('time_clock_adjustments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_clock_audit_logs');
    }
};
