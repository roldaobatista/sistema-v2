<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_order_approvals')) {
            return;
        }

        Schema::create('work_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_notes')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'status'], 'wo_approvals_wo_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_approvals');
    }
};
