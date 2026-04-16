<?php

namespace Tests\Unit\Services\Calibration;

use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\ExcentricityTest;
use App\Services\Calibration\CalibrationWizardService;
use Tests\TestCase;

class CalibrationWizardServiceTest extends TestCase
{
    protected CalibrationWizardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalibrationWizardService;
    }

    public function test_calculate_repeatability_with_valid_measurements(): void
    {
        // Measurements: 10.0, 11.0, 12.0
        // mean: 11.0
        // stdDev: sqrt(((10-11)^2 + (11-11)^2 + (12-11)^2) / 2) = sqrt(2 / 2) = 1.0
        // uncertainty A: stdDev / sqrt(3) = 1.0 / 1.73205081 = 0.577350269 => 0.57735
        $measurements = [10.0, 11.0, 12.0];
        $result = $this->service->calculateRepeatability($measurements);

        $this->assertEquals([
            'mean' => 11.0,
            'std_deviation' => 1.0,
            'uncertainty_type_a' => 0.57735,
            'n' => 3,
        ], $result);
    }

    public function test_calculate_repeatability_filters_out_nulls_and_empty_strings(): void
    {
        // The service should filter out null and '' but keep '11.0' (string representation of number)
        $measurements = [10.0, null, '', '11.0', 12.0];

        $result = $this->service->calculateRepeatability($measurements);

        $this->assertEquals([
            'mean' => 11.0,
            'std_deviation' => 1.0,
            'uncertainty_type_a' => 0.57735,
            'n' => 3,
        ], $result);
    }

    public function test_calculate_repeatability_with_less_than_two_measurements(): void
    {
        // Empty array
        $emptyResult = $this->service->calculateRepeatability([]);
        $this->assertEquals([
            'mean' => null,
            'std_deviation' => null,
            'uncertainty_type_a' => null,
            'n' => 0,
        ], $emptyResult);

        // Single value
        $singleResult = $this->service->calculateRepeatability([10.5]);
        $this->assertEquals([
            'mean' => 10.5,
            'std_deviation' => null,
            'uncertainty_type_a' => null,
            'n' => 1,
        ], $singleResult);
    }

    public function test_calculate_repeatability_with_zero_variance(): void
    {
        // Identical values should have 0 standard deviation and 0 uncertainty
        $measurements = [5.5, 5.5, 5.5, 5.5];

        $result = $this->service->calculateRepeatability($measurements);

        $this->assertEquals([
            'mean' => 5.5,
            'std_deviation' => 0.0,
            'uncertainty_type_a' => 0.0,
            'n' => 4,
        ], $result);
    }

    public function test_prefill_returns_correct_eccentricity_load_and_positions(): void
    {
        $calibration = EquipmentCalibration::factory()->create();

        ExcentricityTest::create([
            'tenant_id' => $calibration->tenant_id,
            'equipment_calibration_id' => $calibration->id,
            'position' => 'front_left',
            'load_applied' => 50.0000,
            'indication' => 50.0100,
            'error' => 0.0100,
            'max_permissible_error' => 0.5000,
            'conforms' => true,
            'position_order' => 1,
        ]);

        ExcentricityTest::create([
            'tenant_id' => $calibration->tenant_id,
            'equipment_calibration_id' => $calibration->id,
            'position' => 'rear_right',
            'load_applied' => 50.0000,
            'indication' => 49.9900,
            'error' => -0.0100,
            'max_permissible_error' => 0.5000,
            'conforms' => true,
            'position_order' => 2,
        ]);

        $result = $this->service->prefillFromPrevious($calibration->equipment);

        $this->assertNotNull($result);
        $this->assertEquals(50.0000, (float) $result['eccentricity_load']);
        $this->assertEquals(['front_left', 'rear_right'], $result['eccentricity_positions']);
    }

    public function test_suggest_measurement_points_does_not_throw_fatal_error(): void
    {
        // Verifica que EmaCalculator está importado corretamente (Task 1.2)
        $equipment = Equipment::factory()->create([
            'capacity' => 100,
            'resolution' => 0.01,
            'precision_class' => 'III',
        ]);

        $result = $this->service->suggestMeasurementPoints($equipment);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }
}
