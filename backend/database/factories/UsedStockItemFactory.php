<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\UsedStockItem;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsedStockItemFactory extends Factory
{
    protected $model = UsedStockItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_order_id' => WorkOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'status' => UsedStockItem::STATUS_PENDING_RETURN,
        ];
    }

    public function returned(): static
    {
        return $this->state(fn () => ['status' => UsedStockItem::STATUS_RETURNED]);
    }
}
