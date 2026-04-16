<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CalibrationReading;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalibrationControlChartTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_returns_empty_chart_data_when_no_calibrations(): void
    {
        $response = $this->getJson("/api/v1/metrology/control-charts/{$this->equipment->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($this->equipment->id, $data['equipment_id']);
        $this->assertEmpty($data['chart_data']);
        $this->assertNull($data['ucl']);
        $this->assertNull($data['lcl']);
        $this->assertNull($data['cl']);
    }

    public function test_returns_chart_data_with_calibrations_having_readings(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $calibration = EquipmentCalibration::create([
                'tenant_id' => $this->tenant->id,
                'equipment_id' => $this->equipment->id,
                'calibration_date' => now()->subDays(50 - ($i * 10)),
                'certificate_number' => "CHART-{$i}",
                'result' => 'approved',
                'performed_by' => $this->user->id,
            ]);
            $this->createCalibrationReadings($calibration, [
                ['reference_value' => 100, 'indication_increasing' => 100 + ($i * 0.1)],
                ['reference_value' => 200, 'indication_increasing' => 200 + ($i * 0.15)],
                ['reference_value' => 500, 'indication_increasing' => 500 + ($i * 0.2)],
            ]);
        }

        $response = $this->getJson("/api/v1/metrology/control-charts/{$this->equipment->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data['chart_data']);
        $this->assertEquals(5, $data['total_calibrations']);
        $this->assertNotNull($data['ucl']);
        $this->assertNotNull($data['lcl']);
        $this->assertNotNull($data['cl']);
        $this->assertNotNull($data['std_dev']);
    }

    public function test_chart_data_has_correct_structure(): void
    {
        $calibration = EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'calibration_date' => now()->subDays(5),
            'certificate_number' => 'STRUCT-001',
            'result' => 'approved',
            'performed_by' => $this->user->id,
        ]);
        $this->createCalibrationReadings($calibration, [
            ['reference_value' => 100, 'indication_increasing' => 100.05],
            ['reference_value' => 200, 'indication_increasing' => 200.10],
        ]);

        $response = $this->getJson("/api/v1/metrology/control-charts/{$this->equipment->id}");

        $response->assertOk();
        $chartPoint = $response->json('data.chart_data.0');
        $this->assertArrayHasKey('calibration_id', $chartPoint);
        $this->assertArrayHasKey('date', $chartPoint);
        $this->assertArrayHasKey('mean_error_pct', $chartPoint);
        $this->assertArrayHasKey('range', $chartPoint);
        $this->assertArrayHasKey('result', $chartPoint);
        $this->assertArrayHasKey('readings_count', $chartPoint);
    }

    public function test_skips_calibrations_without_readings(): void
    {
        // One with readings, one without
        $withReadings = EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'calibration_date' => now()->subDays(10),
            'certificate_number' => 'WITH-READINGS',
            'result' => 'approved',
            'performed_by' => $this->user->id,
        ]);
        $this->createCalibrationReadings($withReadings, [
            ['reference_value' => 100, 'indication_increasing' => 100.02],
        ]);
        EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'calibration_date' => now()->subDays(5),
            'certificate_number' => 'NO-READINGS',
            'result' => 'approved',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/metrology/control-charts/{$this->equipment->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(1, $data['total_calibrations']);
    }

    public function test_control_limits_calculated_correctly_for_single_calibration(): void
    {
        $calibration = EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'calibration_date' => now()->subDays(5),
            'certificate_number' => 'SINGLE',
            'result' => 'approved',
            'performed_by' => $this->user->id,
        ]);
        $this->createCalibrationReadings($calibration, [
            ['reference_value' => 100, 'indication_increasing' => 100.05],
        ]);

        $response = $this->getJson("/api/v1/metrology/control-charts/{$this->equipment->id}");

        $response->assertOk();
        $data = $response->json('data');
        // With single point, std_dev should be 0
        $this->assertEquals(0, $data['std_dev']);
        // UCL and LCL should equal CL since std_dev is 0
        $this->assertEquals($data['cl'], $data['ucl']);
        $this->assertEquals($data['cl'], $data['lcl']);
    }

    public function test_handles_indication_decreasing_when_increasing_is_missing(): void
    {
        $calibration = EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'calibration_date' => now()->subDays(5),
            'certificate_number' => 'ALT-FORMAT',
            'result' => 'approved',
            'performed_by' => $this->user->id,
        ]);
        $this->createCalibrationReadings($calibration, [
            ['reference_value' => 100, 'indication_decreasing' => 100.03],
            ['reference_value' => 200, 'indication_decreasing' => 200.06],
        ]);

        $response = $this->getJson("/api/v1/metrology/control-charts/{$this->equipment->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data['chart_data']);
        $this->assertGreaterThan(0, $data['chart_data'][0]['mean_error_pct']);
    }

    public function test_tenant_isolation_only_shows_own_equipment_calibrations(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherEquipment = Equipment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $calibration = EquipmentCalibration::create([
            'tenant_id' => $otherTenant->id,
            'equipment_id' => $otherEquipment->id,
            'calibration_date' => now()->subDays(5),
            'certificate_number' => 'OTHER-TENANT',
            'result' => 'approved',
            'performed_by' => $this->user->id,
        ]);
        $this->createCalibrationReadings($calibration, [
            ['reference_value' => 100, 'indication_increasing' => 100.02],
        ]);

        // Request for another tenant's equipment -- the WHERE tenant_id filter should return empty
        $response = $this->getJson("/api/v1/metrology/control-charts/{$otherEquipment->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEmpty($data['chart_data']);
    }

    public function test_limits_to_50_calibrations(): void
    {
        for ($i = 0; $i < 55; $i++) {
            $calibration = EquipmentCalibration::create([
                'tenant_id' => $this->tenant->id,
                'equipment_id' => $this->equipment->id,
                'calibration_date' => now()->subDays(55 - $i),
                'certificate_number' => "BULK-{$i}",
                'result' => 'approved',
                'performed_by' => $this->user->id,
            ]);
            $this->createCalibrationReadings($calibration, [
                ['reference_value' => 100, 'indication_increasing' => 100 + (0.01 * $i)],
            ]);
        }

        $response = $this->getJson("/api/v1/metrology/control-charts/{$this->equipment->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertLessThanOrEqual(50, $data['total_calibrations']);
    }

    public function test_nonexistent_equipment_returns_empty_chart(): void
    {
        $response = $this->getJson('/api/v1/metrology/control-charts/999999');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEmpty($data['chart_data']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson("/api/v1/metrology/control-charts/{$this->equipment->id}");

        $response->assertUnauthorized();
    }

    private function createCalibrationReadings(EquipmentCalibration $calibration, array $readings): void
    {
        foreach ($readings as $index => $reading) {
            CalibrationReading::create([
                'tenant_id' => $calibration->tenant_id,
                'equipment_calibration_id' => $calibration->id,
                'reference_value' => $reading['reference_value'],
                'indication_increasing' => $reading['indication_increasing'] ?? null,
                'indication_decreasing' => $reading['indication_decreasing'] ?? null,
                'reading_order' => $index + 1,
                'unit' => 'kg',
            ]);
        }
    }
}
