<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hour_bank_transactions')) {
            return;
        }

        Schema::create('hour_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('journey_entry_id')->nullable();
            $table->string('type', 20); // accrual, usage, expiry, payout
            $table->decimal('hours', 8, 2);
            $table->decimal('balance_before', 8, 2)->default(0);
            $table->decimal('balance_after', 8, 2)->default(0);
            $table->date('reference_date');
            $table->timestamp('expired_at')->nullable();
            $table->unsignedBigInteger('payout_payroll_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('journey_entry_id')->references('id')->on('journey_entries')->nullOnDelete();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'user_id', 'type']);
            $table->index(['tenant_id', 'reference_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hour_bank_transactions');
    }
};
