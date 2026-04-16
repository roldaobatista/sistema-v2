<?php

namespace Tests\Feature\Api\V1\Fleet;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Fleet\FuelLog;
use App\Models\FleetVehicle;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for FuelLogController:
 *   GET    /api/v1/fleet/fuel-logs
 *   POST   /api/v1/fleet/fuel-logs
 *   GET    /api/v1/fleet/fuel-logs/{log}
 *   PUT    /api/v1/fleet/fuel-logs/{log}
 *   DELETE /api/v1/fleet/fuel-logs/{log}
 */
class FuelLogControllerTest extends TestCase
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
            'plate' => 'FUEL-001',
            'brand' => 'Ford',
            'model' => 'Ranger',
            'status' => 'active',
            'odometer_km' => 30000,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'fleet_vehicle_id' => $this->vehicle->id,
            'date' => now()->toDateString(),
            'odometer_km' => 30500,
            'liters' => 45.00,
            'price_per_liter' => 5.89,
            'total_value' => 265.05,
            'fuel_type' => 'diesel',
            'gas_station' => 'Posto Central',
        ], $overrides);
    }

    // ── Authentication ──────────────────────────────────────────

    public function test_unauthenticated_cannot_list_fuel_logs(): void
    {
        $this->app['auth']->forgetGuards();
        $response = $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/fleet/fuel-logs');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_fuel_log(): void
    {
        $this->app['auth']->forgetGuards();
        $response = $this->withHeaders(['Authorization' => ''])->postJson('/api/v1/fleet/fuel-logs', []);
        $response->assertStatus(401);
    }

    // ── GET /api/v1/fleet/fuel-logs ─────────────────────────────

    public function test_index_returns_paginated_fuel_logs(): void
    {
        FuelLog::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'date' => now()->toDateString(),
            'odometer_km' => 30200,
            'liters' => 40.00,
            'price_per_liter' => 5.80,
            'total_value' => 232.00,
        ]);

        $response = $this->getJson('/api/v1/fleet/fuel-logs');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_index_filters_by_vehicle(): void
    {
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'FUEL-002',
            'brand' => 'VW',
            'model' => 'Gol',
            'status' => 'active',
            'odometer_km' => 10000,
        ]);

        FuelLog::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'date' => now()->toDateString(),
            'odometer_km' => 30300,
            'liters' => 35.00,
            'price_per_liter' => 5.70,
            'total_value' => 199.50,
        ]);

        $response = $this->getJson('/api/v1/fleet/fuel-logs?fleet_vehicle_id='.$otherVehicle->id);

        $response->assertStatus(200);
        // No fuel logs for this vehicle
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_filters_by_date_range(): void
    {
        FuelLog::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'date' => '2025-01-15',
            'odometer_km' => 30100,
            'liters' => 30.00,
            'price_per_liter' => 5.60,
            'total_value' => 168.00,
        ]);

        $response = $this->getJson('/api/v1/fleet/fuel-logs?date_from=2025-01-01&date_to=2025-01-31');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_index_only_returns_own_tenant_logs(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTHER-001',
            'brand' => 'Fiat',
            'model' => 'Uno',
            'status' => 'active',
            'odometer_km' => 5000,
        ]);
        FuelLog::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'driver_id' => $this->user->id,
            'date' => now()->toDateString(),
            'odometer_km' => 5100,
            'liters' => 20.00,
            'price_per_liter' => 5.50,
            'total_value' => 110.00,
        ]);

        $response = $this->getJson('/api/v1/fleet/fuel-logs');
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('tenant_id')->unique();
        foreach ($ids as $tid) {
            $this->assertEquals($this->tenant->id, $tid);
        }
    }

    // ── POST /api/v1/fleet/fuel-logs ────────────────────────────

    public function test_store_creates_fuel_log_successfully(): void
    {
        $response = $this->postJson('/api/v1/fleet/fuel-logs', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'fleet_vehicle_id', 'liters', 'total_value']]);

        $this->assertDatabaseHas('fuel_logs', [
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'liters' => 45.00,
        ]);
    }

    public function test_store_updates_vehicle_odometer(): void
    {
        $this->postJson('/api/v1/fleet/fuel-logs', $this->validPayload(['odometer_km' => 30999]));

        $this->assertDatabaseHas('fleet_vehicles', [
            'id' => $this->vehicle->id,
            'odometer_km' => 30999,
        ]);
    }

    public function test_store_calculates_consumption_when_previous_log_exists(): void
    {
        // First log
        FuelLog::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'date' => now()->subDays(5)->toDateString(),
            'odometer_km' => 30000,
            'liters' => 40.00,
            'price_per_liter' => 5.80,
            'total_value' => 232.00,
        ]);

        // Second log — should trigger consumption calculation
        $response = $this->postJson('/api/v1/fleet/fuel-logs', $this->validPayload([
            'odometer_km' => 30400,
            'liters' => 40.00,
        ]));

        $response->assertStatus(201);
        $log = FuelLog::where('tenant_id', $this->tenant->id)
            ->where('odometer_km', 30400)
            ->first();
        $this->assertNotNull($log->consumption_km_l);
        // 400km / 40L = 10 km/L
        $this->assertEquals(10.00, (float) $log->consumption_km_l);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/fleet/fuel-logs', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fleet_vehicle_id', 'date', 'odometer_km', 'liters', 'price_per_liter', 'total_value']);
    }

    public function test_store_validates_vehicle_belongs_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'FOREIGN-01',
            'brand' => 'Nissan',
            'model' => 'Frontier',
            'status' => 'active',
            'odometer_km' => 1000,
        ]);

        $response = $this->postJson('/api/v1/fleet/fuel-logs', $this->validPayload([
            'fleet_vehicle_id' => $foreignVehicle->id,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fleet_vehicle_id']);
    }

    // ── GET /api/v1/fleet/fuel-logs/{log} ───────────────────────

    public function test_show_returns_fuel_log_with_relations(): void
    {
        $log = FuelLog::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'date' => now()->toDateString(),
            'odometer_km' => 30700,
            'liters' => 50.00,
            'price_per_liter' => 6.00,
            'total_value' => 300.00,
        ]);

        $response = $this->getJson("/api/v1/fleet/fuel-logs/{$log->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.liters', '50.00');
    }

    public function test_show_returns_404_for_other_tenant_log(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OT-FUEL',
            'brand' => 'Kia',
            'model' => 'Bongo',
            'status' => 'active',
            'odometer_km' => 2000,
        ]);
        $foreignLog = FuelLog::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'driver_id' => $this->user->id,
            'date' => now()->toDateString(),
            'odometer_km' => 2100,
            'liters' => 25.00,
            'price_per_liter' => 5.50,
            'total_value' => 137.50,
        ]);

        $response = $this->getJson("/api/v1/fleet/fuel-logs/{$foreignLog->id}");

        $response->assertStatus(404);
    }

    // ── PUT /api/v1/fleet/fuel-logs/{log} ───────────────────────

    public function test_update_modifies_fuel_log(): void
    {
        $log = FuelLog::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'date' => now()->toDateString(),
            'odometer_km' => 31000,
            'liters' => 42.00,
            'price_per_liter' => 5.75,
            'total_value' => 241.50,
        ]);

        $response = $this->putJson("/api/v1/fleet/fuel-logs/{$log->id}", [
            'gas_station' => 'Posto Novo',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('fuel_logs', [
            'id' => $log->id,
            'gas_station' => 'Posto Novo',
        ]);
    }

    public function test_update_returns_404_for_other_tenant_log(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'UPDATE-OT',
            'brand' => 'Hyundai',
            'model' => 'HR',
            'status' => 'active',
            'odometer_km' => 8000,
        ]);
        $foreignLog = FuelLog::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'driver_id' => $this->user->id,
            'date' => now()->toDateString(),
            'odometer_km' => 8100,
            'liters' => 30.00,
            'price_per_liter' => 5.60,
            'total_value' => 168.00,
        ]);

        $response = $this->putJson("/api/v1/fleet/fuel-logs/{$foreignLog->id}", ['gas_station' => 'Hack']);

        $response->assertStatus(404);
    }

    // ── DELETE /api/v1/fleet/fuel-logs/{log} ────────────────────

    public function test_destroy_deletes_fuel_log(): void
    {
        $log = FuelLog::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'date' => now()->toDateString(),
            'odometer_km' => 31500,
            'liters' => 38.00,
            'price_per_liter' => 5.85,
            'total_value' => 222.30,
        ]);

        $response = $this->deleteJson("/api/v1/fleet/fuel-logs/{$log->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('fuel_logs', ['id' => $log->id]);
    }

    public function test_destroy_returns_404_for_other_tenant_log(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'DEL-OT',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'status' => 'active',
            'odometer_km' => 50000,
        ]);
        $foreignLog = FuelLog::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'driver_id' => $this->user->id,
            'date' => now()->toDateString(),
            'odometer_km' => 50200,
            'liters' => 45.00,
            'price_per_liter' => 5.90,
            'total_value' => 265.50,
        ]);

        $response = $this->deleteJson("/api/v1/fleet/fuel-logs/{$foreignLog->id}");

        $response->assertStatus(404);
    }
}
