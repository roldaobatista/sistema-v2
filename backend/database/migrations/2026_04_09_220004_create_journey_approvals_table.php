<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journey_approvals')) {
            return;
        }

        Schema::create('journey_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('journey_day_id')->nullable()->constrained();
            $table->string('level'); // operational, hr
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'journey_day_id', 'level']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_approvals');
    }
};
