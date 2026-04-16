<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clt_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('date');
            $table->enum('violation_type', [
                'overtime_limit_exceeded',
                'inter_shift_short',
                'intra_shift_missing',
                'intra_shift_short',
                'dsr_missing',
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->string('description', 500);
            $table->boolean('resolved')->default(false);
            $table->datetime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clt_violations');
    }
};
