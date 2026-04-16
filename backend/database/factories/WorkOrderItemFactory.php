<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderItemFactory extends Factory
{
    protected $model = WorkOrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 10);
        $unitPrice = fake()->randomFloat(2, 50, 2000);
        $total = round($quantity * $unitPrice, 2);

        return [
            'tenant_id' => Tenant::factory(),
            'work_order_id' => WorkOrder::factory(),
            'type' => fake()->randomElement(['product', 'service']),
            'description' => fake()->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => round($unitPrice * 0.6, 2),
            'discount' => 0,
            'total' => $total,
        ];
    }

    public function product(): static
    {
        return $this->state(fn () => ['type' => 'product']);
    }

    public function service(): static
    {
        return $this->state(fn () => ['type' => 'service']);
    }
}
