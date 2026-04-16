<?php

namespace Database\Factories;

use App\Models\SlaPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlaPolicyFactory extends Factory
{
    protected $model = SlaPolicy::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true).' SLA',
            'response_time_minutes' => fake()->randomElement([15, 30, 60, 120, 240]),
            'resolution_time_minutes' => fake()->randomElement([60, 120, 240, 480, 1440]),
            'priority' => fake()->randomElement([
                SlaPolicy::PRIORITY_LOW,
                SlaPolicy::PRIORITY_MEDIUM,
                SlaPolicy::PRIORITY_HIGH,
                SlaPolicy::PRIORITY_CRITICAL,
            ]),
            'is_active' => true,
        ];
    }
}
