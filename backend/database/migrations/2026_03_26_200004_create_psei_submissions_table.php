<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psei_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seal_id')->constrained('inmetro_seals')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipments')->nullOnDelete();
            $table->string('submission_type', 20)->default('automatic')->comment('automatic, manual, retry');
            $table->string('status', 20)->default('queued')->comment('queued, submitting, success, failed, timeout, captcha_blocked');
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->string('protocol_number', 100)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'seal_id']);
            $table->index(['status', 'next_retry_at'], 'idx_psei_retry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psei_submissions');
    }
};
