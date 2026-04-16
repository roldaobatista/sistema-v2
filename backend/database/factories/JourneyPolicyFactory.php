<?php

namespace Database\Factories;

use App\Models\JourneyPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JourneyPolicy>
 */
class JourneyPolicyFactory extends Factory
{
    protected $model = JourneyPolicy::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->word().' '.$this->faker->word().' '.$this->faker->word().' Policy',
            'regime_type' => $this->faker->randomElement(['clt_mensal', 'clt_6meses', 'cct_anual']),
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
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
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
}
