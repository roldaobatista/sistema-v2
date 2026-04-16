<?php

namespace Tests\Feature\Api\V1\Fleet;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\FleetVehicle;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleInspection;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleInspectionControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private FleetVehicle $vehicle;

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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->vehicle = FleetVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'INS-0001',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'status' => 'active',
            'odometer_km' => 50000,
        ]);
    }

    public function test_index_returns_paginated_inspections(): void
    {
        VehicleInspection::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspector_id' => $this->user->id,
            'inspection_date' => now(),
            'odometer_km' => 50500,
            'checklist_data' => ['tires' => 'ok'],
            'status' => 'ok',
        ]);
        VehicleInspection::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspector_id' => $this->user->id,
            'inspection_date' => now()->subDays(7),
            'odometer_km' => 49000,
            'checklist_data' => ['oil' => 'low'],
            'status' => 'issues_found',
        ]);

        $response = $this->getJson('/api/v1/fleet/inspections');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'fleet_vehicle_id', 'inspector_id', 'inspection_date', 'odometer_km', 'status']],
                'meta' => ['current_page', 'per_page'],
            ]);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_index_filters_by_fleet_vehicle_id(): void
    {
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'INS-0002',
            'status' => 'active',
        ]);
        VehicleInspection::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspector_id' => $this->user->id,
            'inspection_date' => now(),
            'odometer_km' => 51000,
            'checklist_data' => [],
            'status' => 'ok',
        ]);
        VehicleInspection::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'inspector_id' => $this->user->id,
            'inspection_date' => now(),
            'odometer_km' => 10000,
            'checklist_data' => [],
            'status' => 'ok',
        ]);

        $response = $this->getJson("/api/v1/fleet/inspections?fleet_vehicle_id={$this->vehicle->id}");

        $response->assertStatus(200);
        $vehicleIds = collect($response->json('data'))->pluck('fleet_vehicle_id')->unique()->toArray();
        $this->assertEquals([$this->vehicle->id], $vehicleIds);
    }

    public function test_index_filters_by_status(): void
    {
        VehicleInspection::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspector_id' => $this->user->id,
            'inspection_date' => now(),
            'odometer_km' => 51500,
            'checklist_data' => [],
            'status' => 'critical',
        ]);
        VehicleInspection::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspector_id' => $this->user->id,
            'inspection_date' => now(),
            'odometer_km' => 52000,
            'checklist_data' => [],
            'status' => 'ok',
        ]);

        $response = $this->getJson('/api/v1/fleet/inspections?status=critical');

        $response->assertStatus(200);
        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals(['critical'], $statuses);
    }

    public function test_store_inspection_successfully(): void
    {
        $response = $this->postJson('/api/v1/fleet/inspections', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspection_date' => '2026-03-09',
            'odometer_km' => 55000,
            'checklist_data' => ['tires' => 'ok', 'oil' => 'ok', 'brakes' => 'ok'],
            'status' => 'ok',
            'observations' => 'Tudo em ordem',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Inspeção registrada')
            ->assertJsonPath('data.fleet_vehicle_id', $this->vehicle->id)
            ->assertJsonPath('data.inspector_id', $this->user->id)
            ->assertJsonPath('data.status', 'ok');

        $this->assertDatabaseHas('vehicle_inspections', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'tenant_id' => $this->tenant->id,
            'odometer_km' => 55000,
        ]);
    }

    public function test_store_inspection_updates_vehicle_odometer(): void
    {
        $this->postJson('/api/v1/fleet/inspections', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspection_date' => '2026-03-09',
            'odometer_km' => 60000,
            'checklist_data' => ['tires' => 'ok'],
            'status' => 'ok',
        ]);

        $this->vehicle->refresh();
        $this->assertEquals(60000, $this->vehicle->odometer_km);
    }

    public function test_store_inspection_validation_requires_fields(): void
    {
        $response = $this->postJson('/api/v1/fleet/inspections', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fleet_vehicle_id', 'inspection_date', 'odometer_km', 'checklist_data', 'status']);
    }

    public function test_store_inspection_validation_invalid_status(): void
    {
        $response = $this->postJson('/api/v1/fleet/inspections', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspection_date' => '2026-03-09',
            'odometer_km' => 55000,
            'checklist_data' => ['tires' => 'ok'],
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_inspection_validates_vehicle_belongs_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-9999',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/fleet/inspections', [
            'fleet_vehicle_id' => $otherVehicle->id,
            'inspection_date' => '2026-03-09',
            'odometer_km' => 10000,
            'checklist_data' => ['tires' => 'ok'],
            'status' => 'ok',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fleet_vehicle_id']);
    }

    public function test_show_inspection_returns_details(): void
    {
        $inspection = VehicleInspection::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspector_id' => $this->user->id,
            'inspection_date' => '2026-03-05',
            'odometer_km' => 53000,
            'checklist_data' => ['brakes' => 'worn'],
            'status' => 'issues_found',
            'observations' => 'Freios precisam troca',
        ]);

        $response = $this->getJson("/api/v1/fleet/inspections/{$inspection->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $inspection->id)
            ->assertJsonPath('data.status', 'issues_found')
            ->assertJsonPath('data.observations', 'Freios precisam troca');
    }

    public function test_show_inspection_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0001',
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $inspection = VehicleInspection::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'inspector_id' => $otherUser->id,
            'inspection_date' => now(),
            'odometer_km' => 10000,
            'checklist_data' => [],
            'status' => 'ok',
        ]);

        $response = $this->getJson("/api/v1/fleet/inspections/{$inspection->id}");

        // BelongsToTenant global scope hides records from other tenants => 404
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_update_inspection_successfully(): void
    {
        $inspection = VehicleInspection::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspector_id' => $this->user->id,
            'inspection_date' => '2026-03-01',
            'odometer_km' => 54000,
            'checklist_data' => ['tires' => 'ok'],
            'status' => 'ok',
        ]);

        $response = $this->putJson("/api/v1/fleet/inspections/{$inspection->id}", [
            'status' => 'issues_found',
            'observations' => 'Pneu dianteiro com desgaste',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Inspeção atualizada');

        $this->assertDatabaseHas('vehicle_inspections', [
            'id' => $inspection->id,
            'status' => 'issues_found',
            'observations' => 'Pneu dianteiro com desgaste',
        ]);
    }

    public function test_update_inspection_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0002',
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $inspection = VehicleInspection::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'inspector_id' => $otherUser->id,
            'inspection_date' => now(),
            'odometer_km' => 20000,
            'checklist_data' => [],
            'status' => 'ok',
        ]);

        $response = $this->putJson("/api/v1/fleet/inspections/{$inspection->id}", [
            'status' => 'critical',
        ]);

        // BelongsToTenant global scope hides records from other tenants => 404
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_destroy_inspection_successfully(): void
    {
        $inspection = VehicleInspection::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'inspector_id' => $this->user->id,
            'inspection_date' => '2026-02-20',
            'odometer_km' => 48000,
            'checklist_data' => [],
            'status' => 'ok',
        ]);

        $response = $this->deleteJson("/api/v1/fleet/inspections/{$inspection->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Inspeção excluída');

        $this->assertDatabaseMissing('vehicle_inspections', ['id' => $inspection->id]);
    }

    public function test_destroy_inspection_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0003',
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $inspection = VehicleInspection::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'inspector_id' => $otherUser->id,
            'inspection_date' => now(),
            'odometer_km' => 5000,
            'checklist_data' => [],
            'status' => 'ok',
        ]);

        $response = $this->deleteJson("/api/v1/fleet/inspections/{$inspection->id}");

        // BelongsToTenant global scope hides records from other tenants => 404
        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('vehicle_inspections', ['id' => $inspection->id]);
    }
}
