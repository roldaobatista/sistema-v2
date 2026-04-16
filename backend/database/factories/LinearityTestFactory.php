<?php

namespace Database\Factories;

use App\Models\EquipmentCalibration;
use App\Models\LinearityTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinearityTest>
 */
class LinearityTestFactory extends Factory
{
    protected $model = LinearityTest::class;

    public function definition(): array
    {
        $referenceValue = $this->faker->randomFloat(4, 10, 150);
        $errorInc = $this->faker->randomFloat(4, -0.5, 0.5);
        $errorDec = $this->faker->randomFloat(4, -0.5, 0.5);
        $indicationInc = $referenceValue + $errorInc;
        $indicationDec = $referenceValue + $errorDec;

        return [
            'equipment_calibration_id' => EquipmentCalibration::factory(),
            'point_order' => $this->faker->numberBetween(1, 5),
            'reference_value' => $referenceValue,
            'unit' => 'kg',
            'indication_increasing' => $indicationInc,
            'indication_decreasing' => $indicationDec,
            'error_increasing' => $errorInc,
            'error_decreasing' => $errorDec,
            'hysteresis' => abs($indicationInc - $indicationDec),
            'max_permissible_error' => 1.0,
            'conforms' => true,
        ];
    }

    public function nonConforming(): static
    {
        return $this->state(fn () => [
            'error_increasing' => 5.0,
            'error_decreasing' => -5.0,
            'conforms' => false,
        ]);
    }
}
