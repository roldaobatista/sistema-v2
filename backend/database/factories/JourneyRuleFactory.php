<?php

namespace Database\Factories;

use App\Models\JourneyRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class JourneyRuleFactory extends Factory
{
    protected $model = JourneyRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true).' Rule',
            // Legado
            'daily_hours' => 8.00,
            'weekly_hours' => 44.00,
            'overtime_weekday_pct' => 50,
            'overtime_weekend_pct' => 100,
            'overtime_holiday_pct' => 100,
            'night_shift_pct' => 20,
            'night_start' => '22:00',
            'night_end' => '05:00',
            'uses_hour_bank' => false,
            'hour_bank_expiry_months' => 6,
            'is_default' => false,
            // Motor Operacional
            'regime_type' => 'clt_mensal',
            'daily_hours_limit' => 480,
            'weekly_hours_limit' => 2640,
            'break_minutes' => 60,
            'displacement_counts_as_work' => false,
            'wait_time_counts_as_work' => true,
            'travel_meal_counts_as_break' => true,
            'auto_suggest_clock_on_displacement' => true,
            'pre_assigned_break' => false,
            'overnight_min_hours' => 11,
            'oncall_multiplier_percent' => 33,
            'saturday_is_overtime' => false,
            'sunday_is_overtime' => true,
            'is_active' => true,
            // Banco de horas
            'compensation_period_days' => 30,
            'max_positive_balance_minutes' => 6000,
            'max_negative_balance_minutes' => 2400,
            'block_on_negative_exceeded' => true,
            'auto_compensate' => false,
            'convert_expired_to_payment' => false,
            'overtime_50_multiplier' => 1.50,
            'overtime_100_multiplier' => 2.00,
            'requires_two_level_approval' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
        ]);
    }

    public function withHourBank(): static
    {
        return $this->state(fn () => [
            'uses_hour_bank' => true,
        ]);
    }

    public function displacementAsWork(): static
    {
        return $this->state(fn () => [
            'displacement_counts_as_work' => true,
        ]);
    }

    public function preAssignedBreak(): static
    {
        return $this->state(fn () => [
            'pre_assigned_break' => true,
        ]);
    }

    public function twelveByThirtySix(): static
    {
        return $this->state(fn () => [
            'name' => '12x36',
            'daily_hours_limit' => 720,
            'weekly_hours_limit' => 2520,
            'saturday_is_overtime' => false,
            'sunday_is_overtime' => false,
        ]);
    }

    public function semestral(): static
    {
        return $this->state(fn () => [
            'regime_type' => 'clt_6meses',
            'compensation_period_days' => 180,
        ]);
    }

    public function autoCompensate(): static
    {
        return $this->state(fn () => [
            'auto_compensate' => true,
        ]);
    }
}
