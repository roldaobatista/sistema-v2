<?php

namespace Database\Factories;

use App\Models\EmployeeBenefit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeBenefitFactory extends Factory
{
    protected $model = EmployeeBenefit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['vt', 'vr', 'va', 'health', 'dental', 'life']),
            'provider' => $this->faker->company(),
            'value' => $this->faker->randomFloat(2, 50, 1500),
            'employee_contribution' => $this->faker->randomFloat(2, 0, 200),
            'start_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'end_date' => null,
            'is_active' => true,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'end_date' => now(),
        ]);
    }
}
