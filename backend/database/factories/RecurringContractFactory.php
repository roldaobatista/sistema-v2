<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\RecurringContract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringContractFactory extends Factory
{
    protected $model = RecurringContract::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'frequency' => fake()->randomElement(['weekly', 'biweekly', 'monthly', 'bimonthly', 'quarterly', 'semiannual', 'annual']),
            'billing_type' => fake()->randomElement(['pre', 'post']),
            'monthly_value' => fake()->randomFloat(2, 100, 5000),
            'start_date' => now(),
            'end_date' => now()->addYear(),
            'next_run_date' => now()->addMonth(),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'is_active' => true,
            'generated_count' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
