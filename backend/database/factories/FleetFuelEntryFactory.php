<?php

namespace Database\Factories;

use App\Models\Fleet;
use App\Models\FleetFuelEntry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class FleetFuelEntryFactory extends Factory
{
    protected $model = FleetFuelEntry::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'fleet_id' => Fleet::factory(),
            'date' => fake()->date(),
            'fuel_type' => fake()->randomElement(['gasoline', 'diesel', 'ethanol']),
            'liters' => fake()->randomFloat(2, 10, 80),
            'cost' => fake()->randomFloat(2, 50, 500),
            'odometer' => fake()->numberBetween(10000, 200000),
            'station' => fake()->company(),
        ];
    }
}
