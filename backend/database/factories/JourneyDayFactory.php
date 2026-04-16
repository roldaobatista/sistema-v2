<?php

namespace Database\Factories;

use App\Models\JourneyDay;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JourneyDay>
 */
class JourneyDayFactory extends Factory
{
    protected $model = JourneyDay::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'reference_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'regime_type' => $this->faker->randomElement(['clt_mensal', 'clt_6meses', 'cct_anual']),
            'total_minutes_worked' => $this->faker->numberBetween(0, 600),
            'total_minutes_overtime' => $this->faker->numberBetween(0, 120),
            'total_minutes_travel' => $this->faker->numberBetween(0, 180),
            'total_minutes_wait' => $this->faker->numberBetween(0, 60),
            'total_minutes_break' => 60,
            'total_minutes_overnight' => 0,
            'total_minutes_oncall' => 0,
            'operational_approval_status' => 'pending',
            'hr_approval_status' => 'pending',
            'is_closed' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'operational_approval_status' => 'approved',
            'operational_approved_at' => now(),
            'hr_approval_status' => 'approved',
            'hr_approved_at' => now(),
            'is_closed' => true,
        ]);
    }

    public function operationalApproved(): static
    {
        return $this->state(fn () => [
            'operational_approval_status' => 'approved',
            'operational_approved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->approved()->state(fn () => [
            'is_closed' => true,
        ]);
    }
}
