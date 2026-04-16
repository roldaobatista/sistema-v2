<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\FleetVehicle;
use App\Models\TimeClockEntry;

class HrFleetIsolationTest extends TenantIsolationTestCase
{
    // ──────────────────────────────────────────────
    // HR — Time Clock Entries
    // ──────────────────────────────────────────────

    public function test_time_clock_entries_isolated_by_tenant(): void
    {
        app()->instance('current_tenant_id', $this->tenantA->id);
        $entryA = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
        ]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $entryB = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->userB->id,
        ]);

        // Verify at model level: tenant A scope only sees own entries
        app()->instance('current_tenant_id', $this->tenantA->id);
        $entries = TimeClockEntry::all();

        $this->assertTrue($entries->contains('id', $entryA->id));
        $this->assertFalse($entries->contains('id', $entryB->id));
    }

    // ──────────────────────────────────────────────
    // Fleet — Vehicles
    // ──────────────────────────────────────────────

    public function test_fleet_vehicles_index_only_returns_own_tenant(): void
    {
        app()->instance('current_tenant_id', $this->tenantA->id);
        FleetVehicle::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        FleetVehicle::factory()->count(2)->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAsTenantA();

        $response = $this->getJson('/api/v1/fleet/vehicles');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_cannot_access_other_tenant_fleet_vehicle(): void
    {
        $vehicle = $this->createForTenantB(FleetVehicle::class);

        $this->actingAsTenantA();

        $response = $this->getJson("/api/v1/fleet/vehicles/{$vehicle->id}");

        $response->assertNotFound();
    }
}
