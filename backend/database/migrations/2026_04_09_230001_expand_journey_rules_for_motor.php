<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journey_rules', function (Blueprint $table) {
            $columns = [
                'daily_hours_limit' => fn ($t) => $t->integer('daily_hours_limit')->default(480),
                'weekly_hours_limit' => fn ($t) => $t->integer('weekly_hours_limit')->default(2640),
                'monthly_hours_limit' => fn ($t) => $t->integer('monthly_hours_limit')->nullable(),
                'break_minutes' => fn ($t) => $t->integer('break_minutes')->default(60),
                'displacement_counts_as_work' => fn ($t) => $t->boolean('displacement_counts_as_work')->default(false),
                'wait_time_counts_as_work' => fn ($t) => $t->boolean('wait_time_counts_as_work')->default(true),
                'travel_meal_counts_as_break' => fn ($t) => $t->boolean('travel_meal_counts_as_break')->default(true),
                'auto_suggest_clock_on_displacement' => fn ($t) => $t->boolean('auto_suggest_clock_on_displacement')->default(true),
                'pre_assigned_break' => fn ($t) => $t->boolean('pre_assigned_break')->default(false),
                'overnight_min_hours' => fn ($t) => $t->integer('overnight_min_hours')->default(11),
                'oncall_multiplier_percent' => fn ($t) => $t->integer('oncall_multiplier_percent')->default(33),
                'overtime_50_percent_limit' => fn ($t) => $t->integer('overtime_50_percent_limit')->nullable(),
                'overtime_100_percent_limit' => fn ($t) => $t->integer('overtime_100_percent_limit')->nullable(),
                'saturday_is_overtime' => fn ($t) => $t->boolean('saturday_is_overtime')->default(false),
                'sunday_is_overtime' => fn ($t) => $t->boolean('sunday_is_overtime')->default(true),
                'custom_rules' => fn ($t) => $t->json('custom_rules')->nullable(),
                'regime_type' => fn ($t) => $t->string('regime_type')->default('clt_mensal'),
                'is_active' => fn ($t) => $t->boolean('is_active')->default(true),
                'compensation_period_days' => fn ($t) => $t->integer('compensation_period_days')->default(30),
                'max_positive_balance_minutes' => fn ($t) => $t->integer('max_positive_balance_minutes')->nullable(),
                'max_negative_balance_minutes' => fn ($t) => $t->integer('max_negative_balance_minutes')->nullable(),
                'block_on_negative_exceeded' => fn ($t) => $t->boolean('block_on_negative_exceeded')->default(true),
                'auto_compensate' => fn ($t) => $t->boolean('auto_compensate')->default(false),
                'convert_expired_to_payment' => fn ($t) => $t->boolean('convert_expired_to_payment')->default(false),
                'overtime_50_multiplier' => fn ($t) => $t->decimal('overtime_50_multiplier', 4, 2)->default(1.50),
                'overtime_100_multiplier' => fn ($t) => $t->decimal('overtime_100_multiplier', 4, 2)->default(2.00),
                'applicable_roles' => fn ($t) => $t->json('applicable_roles')->nullable(),
                'applicable_teams' => fn ($t) => $t->json('applicable_teams')->nullable(),
                'applicable_unions' => fn ($t) => $t->json('applicable_unions')->nullable(),
                'requires_two_level_approval' => fn ($t) => $t->boolean('requires_two_level_approval')->default(true),
                'deleted_at' => fn ($t) => $t->softDeletes(),
            ];

            foreach ($columns as $name => $definition) {
                if (! Schema::hasColumn('journey_rules', $name)) {
                    $definition($table);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('journey_rules', function (Blueprint $table) {
            $cols = [
                'daily_hours_limit', 'weekly_hours_limit', 'monthly_hours_limit', 'break_minutes',
                'displacement_counts_as_work', 'wait_time_counts_as_work', 'travel_meal_counts_as_break',
                'auto_suggest_clock_on_displacement', 'pre_assigned_break', 'overnight_min_hours',
                'oncall_multiplier_percent', 'overtime_50_percent_limit', 'overtime_100_percent_limit',
                'saturday_is_overtime', 'sunday_is_overtime', 'custom_rules', 'regime_type', 'is_active',
                'compensation_period_days', 'max_positive_balance_minutes', 'max_negative_balance_minutes',
                'block_on_negative_exceeded', 'auto_compensate', 'convert_expired_to_payment',
                'overtime_50_multiplier', 'overtime_100_multiplier', 'applicable_roles', 'applicable_teams',
                'applicable_unions', 'requires_two_level_approval', 'deleted_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('journey_rules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
