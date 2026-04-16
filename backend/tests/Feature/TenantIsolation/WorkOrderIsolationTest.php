<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\Customer;
use App\Models\WorkOrder;

class WorkOrderIsolationTest extends TenantIsolationTestCase
{
    // ──────────────────────────────────────────────
    // Work Orders
    // ──────────────────────────────────────────────

    public function test_work_orders_index_only_returns_own_tenant(): void
    {
        app()->instance('current_tenant_id', $this->tenantA->id);
        $customerA = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);
        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $customerA->id,
        ]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);
        WorkOrder::factory()->count(2)->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $customerB->id,
        ]);

        $this->actingAsTenantA();

        $response = $this->getJson('/api/v1/work-orders');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_cannot_access_other_tenant_work_order(): void
    {
        app()->instance('current_tenant_id', $this->tenantB->id);
        $customerB = Customer::factory()->create();
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customerB->id,
        ]);

        $this->actingAsTenantA();

        $response = $this->getJson("/api/v1/work-orders/{$wo->id}");

        $response->assertNotFound();
    }

    public function test_cannot_update_other_tenant_work_order(): void
    {
        app()->instance('current_tenant_id', $this->tenantB->id);
        $customerB = Customer::factory()->create();
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customerB->id,
        ]);

        $this->actingAsTenantA();

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}", [
            'description' => 'Hijacked',
        ]);

        $response->assertNotFound();
    }
}
