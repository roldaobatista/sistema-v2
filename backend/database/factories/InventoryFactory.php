<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'warehouse_id' => Warehouse::factory(),
            'reference' => 'INV-'.fake()->dateTimeBetween('-30 days', 'now')->format('d/m/Y'),
            'status' => 'open',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
