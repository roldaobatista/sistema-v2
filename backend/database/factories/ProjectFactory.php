<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'crm_deal_id' => null,
            'created_by' => User::factory(),
            'code' => Project::generateCode(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['planning', 'active', 'on_hold', 'completed', 'cancelled']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'start_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'end_date' => fake()->dateTimeBetween('+1 month', '+3 months'),
            'actual_start_date' => null,
            'actual_end_date' => null,
            'budget' => fake()->randomFloat(2, 1000, 50000),
            'spent' => fake()->randomFloat(2, 0, 10000),
            'progress_percent' => fake()->randomFloat(2, 0, 100),
            'billing_type' => fake()->randomElement(['milestone', 'hourly', 'fixed_price']),
            'hourly_rate' => fake()->randomFloat(2, 80, 250),
            'tags' => null,
            'manager_id' => User::factory(),
        ];
    }
}
