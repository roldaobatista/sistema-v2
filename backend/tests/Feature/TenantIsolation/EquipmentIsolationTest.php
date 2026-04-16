<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\Equipment;

class EquipmentIsolationTest extends TenantIsolationTestCase
{
    // ──────────────────────────────────────────────
    // Equipment
    // ──────────────────────────────────────────────

    public function test_equipments_index_only_returns_own_tenant(): void
    {
        app()->instance('current_tenant_id', $this->tenantA->id);
        Equipment::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        Equipment::factory()->count(2)->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAsTenantA();

        $response = $this->getJson('/api/v1/equipments');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_cannot_access_other_tenant_equipment(): void
    {
        $equipment = $this->createForTenantB(Equipment::class);

        $this->actingAsTenantA();

        $response = $this->getJson("/api/v1/equipments/{$equipment->id}");

        $response->assertNotFound();
    }

    public function test_cannot_update_other_tenant_equipment(): void
    {
        $equipment = $this->createForTenantB(Equipment::class);

        $this->actingAsTenantA();

        $response = $this->putJson("/api/v1/equipments/{$equipment->id}", [
            'name' => 'Hijacked Equipment',
        ]);

        $response->assertNotFound();
    }
}
