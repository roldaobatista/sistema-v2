<?php

namespace Database\Factories;

use App\Models\EquipmentCalibration;
use App\Models\ExcentricityTest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExcentricityTestFactory extends Factory
{
    protected $model = ExcentricityTest::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'equipment_calibration_id' => EquipmentCalibration::factory(),
            'load_applied' => fake()->randomFloat(2, 10, 500),
            'position' => fake()->randomElement(['front_left', 'front_right', 'center', 'rear_left', 'rear_right']),
            'indication' => fake()->randomFloat(4, 10, 500),
            'error' => fake()->randomFloat(4, -0.5, 0.5),
            'max_permissible_error' => fake()->randomFloat(4, 0.01, 1),
            'conforms' => fake()->boolean(80),
            'position_order' => fake()->numberBetween(1, 5),
        ];
    }
}
