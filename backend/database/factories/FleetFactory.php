<?php

namespace Database\Factories;

use App\Models\Fleet;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class FleetFactory extends Factory
{
    protected $model = Fleet::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plate' => strtoupper(fake()->bothify('???-#?##')),
            'brand' => fake()->randomElement(['Toyota', 'Ford', 'Chevrolet', 'Volkswagen', 'Fiat']),
            'model' => fake()->word(),
            'year' => (string) fake()->numberBetween(2015, 2026),
            'color' => fake()->safeColorName(),
            'type' => fake()->randomElement(['car', 'truck', 'van', 'motorcycle']),
            'status' => 'active',
            'mileage' => fake()->randomFloat(2, 0, 200000),
            'is_active' => true,
        ];
    }
}
