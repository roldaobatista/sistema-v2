<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Equipment Module Tests — validates CRUD, calibration tracking,
 * alerts, dashboard, and document management.
 */
class EquipmentExtendedTest extends TestCase
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
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_equipment_dashboard_returns_statistics(): void
    {
        $response = $this->getJson('/api/v1/equipments-dashboard');
        $response->assertOk();
    }

    public function test_equipment_alerts_returns_list(): void
    {
        $response = $this->getJson('/api/v1/equipments-alerts');
        $response->assertOk();
    }

    public function test_equipment_constants_returns_enums(): void
    {
        $response = $this->getJson('/api/v1/equipments-constants');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    public function test_create_equipment_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'brand' => 'Toledo',
            'model' => 'Prix 3',
            'serial_number' => 'SN-12345',
            'type' => 'balanca',
            'capacity' => '30kg',
            'location' => 'Galpão A',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('equipments', [
            'serial_number' => 'SN-12345',
            'brand' => 'Toledo',
        ]);
    }

    public function test_equipment_list_with_search(): void
    {
        $response = $this->getJson('/api/v1/equipments?search=Toledo');
        $response->assertOk();
    }

    public function test_equipment_export_csv(): void
    {
        $response = $this->getJson('/api/v1/equipments-export');

        $response->assertOk();
    }

    // ── CALIBRATION HISTORY ──

    public function test_calibration_history_for_equipment(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson("/api/v1/equipments/{$equipment->id}/calibrations");
        $response->assertOk();
    }

    public function test_add_calibration_to_equipment(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson("/api/v1/equipments/{$equipment->id}/calibrations", [
            'calibration_date' => now()->format('Y-m-d'),
            'calibration_type' => 'externa',
            'certificate_number' => 'CERT-001',
            'result' => 'aprovado',
        ]);

        $response->assertCreated();
    }
}
