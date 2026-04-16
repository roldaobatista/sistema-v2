<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journey_policies')) {
            return;
        }

        Schema::create('journey_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('regime_type')->default('clt_mensal');
            $table->integer('daily_hours_limit')->default(480);
            $table->integer('weekly_hours_limit')->default(2640);
            $table->integer('monthly_hours_limit')->nullable();
            $table->integer('break_minutes')->default(60);
            $table->boolean('displacement_counts_as_work')->default(false);
            $table->boolean('wait_time_counts_as_work')->default(true);
            $table->boolean('travel_meal_counts_as_break')->default(true);
            $table->boolean('auto_suggest_clock_on_displacement')->default(true);
            $table->boolean('pre_assigned_break')->default(false);
            $table->integer('overnight_min_hours')->default(11);
            $table->integer('oncall_multiplier_percent')->default(33);
            $table->integer('overtime_50_percent_limit')->nullable();
            $table->integer('overtime_100_percent_limit')->nullable();
            $table->boolean('saturday_is_overtime')->default(false);
            $table->boolean('sunday_is_overtime')->default(true);
            $table->json('custom_rules')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_policies');
    }
};
