<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'status' => StockTransfer::STATUS_PENDING_ACCEPTANCE,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => StockTransfer::STATUS_COMPLETED]);
    }
}
