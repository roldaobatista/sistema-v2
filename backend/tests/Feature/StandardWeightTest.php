<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\StandardWeight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StandardWeightTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);

        // Auth first
        Sanctum::actingAs($this->user, ['*']);

        // Then set Spatie team context + role AFTER auth
        setPermissionsTeamId($this->tenant->id);
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->user->assignRole($role);

        // Clear Spatie cache to ensure roles are re-read
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ── CRUD ──

    public function test_can_create_standard_weight(): void
    {
        $data = [
            'nominal_value' => 20.0000,
            'unit' => 'kg',
            'serial_number' => 'SN-PP-001',
            'manufacturer' => 'Mettler Toledo',
            'precision_class' => 'M1',
            'material' => 'Aço Inox',
            'shape' => 'cylindrical',
            'certificate_number' => 'CERT-2026-001',
            'certificate_date' => '2026-01-01',
            'certificate_expiry' => '2027-01-01',
            'laboratory' => 'IPT Metrologia',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/v1/standard-weights', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.serial_number', 'SN-PP-001')
            ->assertJsonPath('data.nominal_value', '20.0000');

        $this->assertDatabaseHas('standard_weights', [
            'tenant_id' => $this->tenant->id,
            'serial_number' => 'SN-PP-001',
            'precision_class' => 'M1',
        ]);
    }

    public function test_standard_weight_code_is_auto_generated(): void
    {
        $response = $this->postJson('/api/v1/standard-weights', [
            'nominal_value' => 10,
            'unit' => 'kg',
        ]);

        $response->assertStatus(201);
        $this->assertStringStartsWith('PP-', $response->json('data.code'));
    }

    public function test_can_list_standard_weights(): void
    {
        StandardWeight::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/standard-weights');

        $response->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_can_show_standard_weight(): void
    {
        $weight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson("/api/v1/standard-weights/{$weight->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $weight->id);
    }

    public function test_can_update_standard_weight(): void
    {
        $weight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->putJson("/api/v1/standard-weights/{$weight->id}", [
            'manufacturer' => 'Updated Manufacturer',
            'precision_class' => 'E1',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('standard_weights', [
            'id' => $weight->id,
            'manufacturer' => 'Updated Manufacturer',
            'precision_class' => 'E1',
        ]);
    }

    public function test_can_delete_standard_weight_without_calibrations(): void
    {
        $weight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->deleteJson("/api/v1/standard-weights/{$weight->id}");

        $response->assertOk();
        $this->assertSoftDeleted('standard_weights', ['id' => $weight->id]);
    }

    public function test_cannot_delete_standard_weight_with_calibrations(): void
    {
        $weight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $calibration = $equipment->calibrations()->create([
            'tenant_id' => $this->tenant->id,
            'calibration_date' => now(),
            'calibration_type' => 'externa',
            'result' => 'aprovado',
            'performed_by' => $this->user->id,
        ]);

        $calibration->standardWeights()->attach($weight->id);

        $response = $this->deleteJson("/api/v1/standard-weights/{$weight->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('standard_weights', ['id' => $weight->id, 'deleted_at' => null]);
    }

    // ── Tenant Isolation ──

    public function test_standard_weights_isolated_by_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        StandardWeight::factory()->create([
            'tenant_id' => $otherTenant->id,
            'serial_number' => 'OTHER-TENANT-PP',
        ]);

        StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'serial_number' => 'MY-TENANT-PP',
        ]);

        $response = $this->getJson('/api/v1/standard-weights');

        $response->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_cannot_access_other_tenant_weight(): void
    {
        $otherTenant = Tenant::factory()->create();
        $weight = StandardWeight::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->getJson("/api/v1/standard-weights/{$weight->id}");

        // BelongsToTenant global scope makes route model binding return 404
        // (model not found in current tenant scope) — this is correct behavior
        $response->assertStatus(404);
    }

    // ── Search & Filters ──

    public function test_search_by_serial_number(): void
    {
        StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'serial_number' => 'UNIQUE-SN-999',
        ]);

        StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'serial_number' => 'OTHER-SN-111',
        ]);

        $response = $this->getJson('/api/v1/standard-weights?search=UNIQUE');

        $response->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_filter_by_status(): void
    {
        StandardWeight::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => StandardWeight::STATUS_OUT_OF_SERVICE,
        ]);

        $response = $this->getJson('/api/v1/standard-weights?status=active');

        $response->assertOk()
            ->assertJsonPath('total', 1);
    }

    // ── Expiring Alerts ──

    public function test_expiring_endpoint_returns_alerts(): void
    {
        StandardWeight::factory()->expiring(15)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        StandardWeight::factory()->expired()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        StandardWeight::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'certificate_expiry' => now()->addYear(),
        ]);

        $response = $this->getJson('/api/v1/standard-weights/expiring?days=30');

        $response->assertOk()
            ->assertJsonPath('data.expiring_count', 1)
            ->assertJsonPath('data.expired_count', 1);
    }

    // ── Constants ──

    public function test_constants_returns_all_options(): void
    {
        $response = $this->getJson('/api/v1/standard-weights/constants');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['statuses', 'precision_classes', 'units', 'shapes']]);
    }

    // ── Calibration Integration ──

    public function test_calibration_can_attach_standard_weights(): void
    {
        $weight1 = StandardWeight::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $weight2 = StandardWeight::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson("/api/v1/equipments/{$equipment->id}/calibrations", [
            'calibration_date' => now()->toDateString(),
            'calibration_type' => 'externa',
            'result' => 'aprovado',
            'standard_weight_ids' => [$weight1->id, $weight2->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'calibration.standard_weights');

        $this->assertDatabaseHas('calibration_standard_weight', [
            'equipment_calibration_id' => $response->json('data.calibration.id'),
            'standard_weight_id' => $weight1->id,
        ]);

        $this->assertDatabaseHas('calibration_standard_weight', [
            'equipment_calibration_id' => $response->json('data.calibration.id'),
            'standard_weight_id' => $weight2->id,
        ]);
    }

    public function test_calibration_history_includes_standard_weights(): void
    {
        $weight = StandardWeight::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $calibration = $equipment->calibrations()->create([
            'tenant_id' => $this->tenant->id,
            'calibration_date' => now(),
            'calibration_type' => 'interna',
            'result' => 'aprovado',
            'performed_by' => $this->user->id,
        ]);

        $calibration->standardWeights()->attach($weight->id);

        $response = $this->getJson("/api/v1/equipments/{$equipment->id}/calibrations");

        $response->assertOk()
            ->assertJsonCount(1, 'data.calibrations')
            ->assertJsonPath('data.calibrations.0.standard_weights.0.id', $weight->id);
    }

    // ── Export ──

    public function test_can_export_csv(): void
    {
        StandardWeight::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->get('/api/v1/standard-weights/export');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    // ── Validation ──

    public function test_create_requires_nominal_value_and_unit(): void
    {
        $response = $this->postJson('/api/v1/standard-weights', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nominal_value', 'unit']);
    }

    public function test_invalid_precision_class_rejected(): void
    {
        $response = $this->postJson('/api/v1/standard-weights', [
            'nominal_value' => 10,
            'unit' => 'kg',
            'precision_class' => 'INVALID',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['precision_class']);
    }
}
