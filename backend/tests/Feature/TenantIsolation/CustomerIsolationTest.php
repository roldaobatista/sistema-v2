<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\Customer;

class CustomerIsolationTest extends TenantIsolationTestCase
{
    // ──────────────────────────────────────────────
    // Customers
    // ──────────────────────────────────────────────

    public function test_customers_index_only_returns_own_tenant(): void
    {
        app()->instance('current_tenant_id', $this->tenantA->id);
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        Customer::factory()->count(2)->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAsTenantA();

        $response = $this->getJson('/api/v1/customers');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_cannot_access_other_tenant_customer(): void
    {
        $customer = $this->createForTenantB(Customer::class);

        $this->actingAsTenantA();

        $response = $this->getJson("/api/v1/customers/{$customer->id}");

        $response->assertNotFound();
    }

    public function test_cannot_update_other_tenant_customer(): void
    {
        $customer = $this->createForTenantB(Customer::class);

        $this->actingAsTenantA();

        $response = $this->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Hijacked Customer',
        ]);

        $response->assertNotFound();
    }

    public function test_cannot_delete_other_tenant_customer(): void
    {
        $customer = $this->createForTenantB(Customer::class);

        $this->actingAsTenantA();

        $response = $this->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertNotFound();
    }
}
