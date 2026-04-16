<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Básico, Profissional, Enterprise
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('annual_price', 10, 2)->default(0);
            $table->json('modules')->nullable(); // ['financial', 'calibration', 'fleet', ...]
            $table->integer('max_users')->default(5);
            $table->integer('max_work_orders_month')->nullable(); // null = unlimited
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('saas_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('saas_plans');
            $table->string('status')->default('trial'); // trial, active, past_due, cancelled, expired
            $table->string('billing_cycle')->default('monthly'); // monthly, annual
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->date('started_at');
            $table->date('trial_ends_at')->nullable();
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->date('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('payment_gateway')->nullable(); // asaas, stripe, manual
            $table->string('gateway_subscription_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Add plan_id to tenants
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('current_plan_id')->nullable()->after('status')->constrained('saas_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_plan_id');
        });
        Schema::dropIfExists('saas_subscriptions');
        Schema::dropIfExists('saas_plans');
    }
};
