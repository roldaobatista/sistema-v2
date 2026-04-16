<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => fake()->randomFloat(2, 1, 50),
            'type' => 'entry',
            'unit_cost' => fake()->randomFloat(2, 1, 100),
            'reference' => 'FAC-'.fake()->unique()->numerify('######'),
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
