<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerComplaint;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerComplaint>
 */
class CustomerComplaintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['service', 'certificate', 'delay', 'billing']),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => 'open',
        ];
    }
}
