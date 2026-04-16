<?php

namespace Database\Factories;

use App\Models\EquipmentCalibration;
use App\Models\RepeatabilityTest;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepeatabilityTestFactory extends Factory
{
    protected $model = RepeatabilityTest::class;

    public function definition(): array
    {
        $measurements = [];
        for ($i = 1; $i <= 10; $i++) {
            $measurements["measurement_{$i}"] = fake()->randomFloat(4, 99, 101);
        }

        $values = array_values($measurements);
        $mean = round(array_sum($values) / count($values), 4);
        $sumSquaredDiffs = array_reduce(
            $values,
            fn (float $carry, float $v) => $carry + ($v - $mean) ** 2,
            0.0
        );
        $stdDev = round(sqrt($sumSquaredDiffs / 9), 6);
        $uncertaintyTypeA = round($stdDev / sqrt(10), 6);

        return array_merge($measurements, [
            'tenant_id' => null,
            'equipment_calibration_id' => EquipmentCalibration::factory(),
            'load_value' => fake()->randomFloat(4, 50, 500),
            'unit' => 'kg',
            'mean' => $mean,
            'std_deviation' => $stdDev,
            'uncertainty_type_a' => $uncertaintyTypeA,
        ]);
    }
}
