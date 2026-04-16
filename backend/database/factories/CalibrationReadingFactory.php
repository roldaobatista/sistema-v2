<?php

namespace Database\Factories;

use App\Models\CalibrationReading;
use App\Models\EquipmentCalibration;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalibrationReadingFactory extends Factory
{
    protected $model = CalibrationReading::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'equipment_calibration_id' => EquipmentCalibration::factory(),
            'reference_value' => fake()->randomFloat(4, 1, 1000),
            'indication_increasing' => fake()->randomFloat(4, 1, 1000),
            'indication_decreasing' => null,
            'error' => fake()->randomFloat(4, -1, 1),
            'expanded_uncertainty' => fake()->randomFloat(4, 0, 0.5),
            'k_factor' => 2.0,
            'correction' => fake()->randomFloat(4, -1, 1),
            'reading_order' => fake()->numberBetween(1, 10),
            'repetition' => 1,
            'unit' => 'kg',
        ];
    }
}
