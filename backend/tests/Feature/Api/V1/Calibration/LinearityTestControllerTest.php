<?php

namespace Tests\Feature\Api\V1\Calibration;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\LinearityTest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LinearityTestControllerTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $user;

    private EquipmentCalibration $calibration;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
        $this->calibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment->id,
            'precision_class' => 'III',
            'verification_division_e' => 0.1,
            'verification_type' => 'initial',
        ]);
    }

    // ─── Store ───────────────────────────────────────────────────

    public function test_store_creates_linearity_points_with_valid_data(): void
    {
        $payload = [
            'points' => [
                ['reference_value' => 30.0, 'indication_increasing' => 30.02, 'indication_decreasing' => 30.01],
                ['reference_value' => 75.0, 'indication_increasing' => 75.05, 'indication_decreasing' => 74.98],
                ['reference_value' => 150.0, 'indication_increasing' => 150.08, 'indication_decreasing' => 150.02],
            ],
        ];

        $response = $this->postJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity", $payload);

        $response->assertStatus(201);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data' => [['id', 'point_order', 'reference_value', 'unit',
            'indication_increasing', 'indication_decreasing', 'error_increasing', 'error_decreasing',
            'hysteresis', 'max_permissible_error', 'conforms']]]);

        $this->assertDatabaseCount('linearity_tests', 3);
    }

    public function test_store_calculates_errors_correctly(): void
    {
        $payload = [
            'points' => [
                ['reference_value' => 50.0, 'indication_increasing' => 50.03, 'indication_decreasing' => 49.98],
            ],
        ];

        $this->postJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity", $payload)
            ->assertStatus(201);

        $test = LinearityTest::first();
        // error_increasing = 50.03 - 50.0 = 0.03
        $this->assertEqualsWithDelta(0.03, (float) $test->error_increasing, 0.001);
        // error_decreasing = 49.98 - 50.0 = -0.02
        $this->assertEqualsWithDelta(-0.02, (float) $test->error_decreasing, 0.001);
        // hysteresis = |50.03 - 49.98| = 0.05
        $this->assertEqualsWithDelta(0.05, (float) $test->hysteresis, 0.001);
    }

    public function test_store_marks_conforming_when_within_ema(): void
    {
        // Classe III, e=0.1, load=50 → 500e → EMA = 0.5*e = 0.05
        $payload = [
            'points' => [
                ['reference_value' => 50.0, 'indication_increasing' => 50.04, 'indication_decreasing' => 50.03],
            ],
        ];

        $this->postJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity", $payload)
            ->assertStatus(201);

        $test = LinearityTest::first();
        $this->assertTrue($test->conforms);
        $this->assertNotNull($test->max_permissible_error);
    }

    public function test_store_marks_non_conforming_when_outside_ema(): void
    {
        // Classe III, e=0.1, load=50 → 500e → EMA = 0.05
        // error = 0.10, which is > 0.05
        $payload = [
            'points' => [
                ['reference_value' => 50.0, 'indication_increasing' => 50.10, 'indication_decreasing' => 49.90],
            ],
        ];

        $this->postJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity", $payload)
            ->assertStatus(201);

        $test = LinearityTest::first();
        $this->assertFalse($test->conforms);
    }

    public function test_hysteresis_calculated_correctly(): void
    {
        $payload = [
            'points' => [
                ['reference_value' => 100.0, 'indication_increasing' => 100.05, 'indication_decreasing' => 99.95],
            ],
        ];

        $this->postJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity", $payload)
            ->assertStatus(201);

        $test = LinearityTest::first();
        // hysteresis = |100.05 - 99.95| = 0.10
        $this->assertEqualsWithDelta(0.10, (float) $test->hysteresis, 0.001);
    }

    public function test_ema_differs_by_class_and_verification_type(): void
    {
        // Class I, e=0.001, load=100 → 100000e → EMA = 1.0*e = 0.001
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
        $classICalibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment->id,
            'precision_class' => 'I',
            'verification_division_e' => 0.001,
            'verification_type' => 'in_use',
        ]);

        $payload = [
            'points' => [
                ['reference_value' => 100.0, 'indication_increasing' => 100.001, 'indication_decreasing' => 100.0],
            ],
        ];

        $this->postJson("/api/v1/metrology/calibration/{$classICalibration->id}/linearity", $payload)
            ->assertStatus(201);

        $test = LinearityTest::where('equipment_calibration_id', $classICalibration->id)->first();
        // in_use: EMA = 2 * 1.0 * 0.001 = 0.002
        $this->assertEqualsWithDelta(0.002, (float) $test->max_permissible_error, 0.0001);
    }

    public function test_validation_422_reference_value_required(): void
    {
        $payload = [
            'points' => [
                ['indication_increasing' => 50.0],
            ],
        ];

        $this->postJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['points.0.reference_value']);
    }

    public function test_cross_tenant_returns_404(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'current_tenant_id' => $this->otherTenant->id,
        ]);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherEquipment = Equipment::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $otherCalibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'equipment_id' => $otherEquipment->id,
        ]);

        $payload = ['points' => [['reference_value' => 50.0]]];

        $this->postJson("/api/v1/metrology/calibration/{$otherCalibration->id}/linearity", $payload)
            ->assertStatus(404);
    }

    public function test_permission_403_without_calibration_permission(): void
    {
        // Re-enable the CheckPermission middleware for this specific test
        $this->withMiddleware(CheckPermission::class);

        $limitedUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        // User has NO permissions assigned — middleware should block
        Sanctum::actingAs($limitedUser, ['*']);

        $payload = ['points' => [['reference_value' => 50.0]]];

        $this->postJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity", $payload)
            ->assertStatus(403);
    }

    public function test_batch_replace_deletes_previous_points(): void
    {
        // First batch
        $this->postJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity", [
            'points' => [
                ['reference_value' => 30.0, 'indication_increasing' => 30.01],
                ['reference_value' => 60.0, 'indication_increasing' => 60.02],
            ],
        ])->assertStatus(201);

        $this->assertDatabaseCount('linearity_tests', 2);

        // Second batch replaces
        $this->postJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity", [
            'points' => [
                ['reference_value' => 50.0, 'indication_increasing' => 50.01],
            ],
        ])->assertStatus(201);

        $this->assertDatabaseCount('linearity_tests', 1);
        $this->assertDatabaseHas('linearity_tests', ['reference_value' => '50.0000']);
    }

    // ─── Index ───────────────────────────────────────────────────

    public function test_index_returns_linearity_tests_ordered(): void
    {
        LinearityTest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_calibration_id' => $this->calibration->id,
            'point_order' => 2,
            'reference_value' => 75.0,
        ]);
        LinearityTest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_calibration_id' => $this->calibration->id,
            'point_order' => 1,
            'reference_value' => 30.0,
        ]);

        $response = $this->getJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $data = $response->json('data');
        $this->assertEquals(1, $data[0]['point_order']);
        $this->assertEquals(2, $data[1]['point_order']);
    }

    // ─── DestroyAll ──────────────────────────────────────────────

    public function test_destroy_all_removes_linearity_tests(): void
    {
        LinearityTest::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'equipment_calibration_id' => $this->calibration->id,
        ]);

        $this->deleteJson("/api/v1/metrology/calibration/{$this->calibration->id}/linearity")
            ->assertOk();

        $this->assertDatabaseCount('linearity_tests', 0);
    }
}
