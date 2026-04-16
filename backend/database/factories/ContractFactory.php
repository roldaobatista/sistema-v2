<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', 'now');
        $end = fake()->dateTimeBetween($start, '+12 months');

        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'number' => 'CT-'.str_pad(fake()->unique()->numberBetween(1, 9999), 5, '0', STR_PAD_LEFT),
            'name' => fake()->sentence(3),
            'description' => fake()->optional(0.5)->paragraph(),
            'status' => 'active',
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->subMonth()->format('Y-m-d'),
            'is_active' => false,
        ]);
    }
}
