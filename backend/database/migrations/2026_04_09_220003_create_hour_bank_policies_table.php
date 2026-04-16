<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hour_bank_policies')) {
            return;
        }

        Schema::create('hour_bank_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('regime_type')->default('individual_mensal');
            $table->integer('compensation_period_days')->default(30);
            $table->integer('max_positive_balance_minutes')->nullable();
            $table->integer('max_negative_balance_minutes')->nullable();
            $table->boolean('block_on_negative_exceeded')->default(true);
            $table->boolean('auto_compensate')->default(false);
            $table->boolean('convert_expired_to_payment')->default(false);
            $table->decimal('overtime_50_multiplier', 4, 2)->default(1.50);
            $table->decimal('overtime_100_multiplier', 4, 2)->default(2.00);
            $table->json('applicable_roles')->nullable();
            $table->json('applicable_teams')->nullable();
            $table->json('applicable_unions')->nullable();
            $table->boolean('requires_two_level_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hour_bank_policies');
    }
};
