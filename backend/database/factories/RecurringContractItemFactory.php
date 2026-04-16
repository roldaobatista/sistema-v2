<?php

namespace Database\Factories;

use App\Models\RecurringContract;
use App\Models\RecurringContractItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringContractItemFactory extends Factory
{
    protected $model = RecurringContractItem::class;

    public function definition(): array
    {
        return [
            'recurring_contract_id' => RecurringContract::factory(),
            'type' => fake()->randomElement(['service', 'product']),
            'description' => fake()->sentence(4),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_price' => fake()->randomFloat(2, 50, 1000),
        ];
    }
}
