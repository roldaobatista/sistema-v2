<?php

namespace Database\Factories;

use App\Models\FleetVehicle;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetVehicle>
 */
class FleetVehicleFactory extends Factory
{
    protected $model = FleetVehicle::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plate' => strtoupper(fake()->bothify('???-####')),
            'brand' => fake()->company(),
            'model' => fake()->word(),
            'year' => (int) fake()->numberBetween(2018, 2026),
            'type' => 'car',
            'fuel_type' => 'flex',
            'odometer_km' => fake()->numberBetween(1_000, 200_000),
            'status' => 'active',
        ];
    }
}
