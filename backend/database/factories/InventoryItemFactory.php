<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'inventory_id' => Inventory::factory(),
            'product_id' => Product::factory(),
            'expected_quantity' => fake()->numberBetween(0, 100),
            'counted_quantity' => fake()->numberBetween(0, 100),
        ];
    }
}
