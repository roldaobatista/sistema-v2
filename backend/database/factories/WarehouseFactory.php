<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company().' Warehouse',
            'code' => strtoupper(fake()->unique()->bothify('WH-####')),
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ];
    }

    public function vehicle(): static
    {
        return $this->state(fn () => [
            'type' => Warehouse::TYPE_VEHICLE,
        ]);
    }

    public function technician(): static
    {
        return $this->state(fn () => [
            'type' => Warehouse::TYPE_TECHNICIAN,
        ]);
    }
}
