<?php

namespace Database\Factories;

use App\Models\RoutePlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutePlan>
 */
class RoutePlanFactory extends Factory
{
    protected $model = RoutePlan::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'technician_id' => User::factory(),
            'plan_date' => fake()->date(),
            'stops' => [
                ['work_order_id' => 1, 'order' => 1],
            ],
            'total_distance_km' => fake()->randomFloat(2, 0, 100),
            'estimated_duration_min' => fake()->numberBetween(15, 480),
            'status' => fake()->randomElement(['planned', 'in_progress', 'completed']),
        ];
    }
}
