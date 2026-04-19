<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\Customer;
use App\Support\TenantSafeQuery;
use InvalidArgumentException;

class RawQueryIsolationTest extends TenantIsolationTestCase
{
    private function createCustomersForBothTenants(): array
    {
        app()->instance('current_tenant_id', $this->tenantA->id);
        $customerA = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);

        // Reset context to tenant A
        app()->instance('current_tenant_id', $this->tenantA->id);

        return [$customerA, $customerB];
    }

    public function test_eloquent_global_scope_filters_by_tenant(): void
    {
        [$customerA, $customerB] = $this->createCustomersForBothTenants();

        $this->actingAsTenantA();

        $customers = Customer::all();

        $this->assertTrue($customers->contains('id', $customerA->id));
        $this->assertFalse($customers->contains('id', $customerB->id));
        $this->assertCount(1, $customers->where('tenant_id', $this->tenantA->id));
    }

    public function test_tenant_safe_raw_query_respects_isolation(): void
    {
        [$customerA, $customerB] = $this->createCustomersForBothTenants();

        $this->actingAsTenantA();

        $customers = TenantSafeQuery::table('customers')->get();

        $this->assertCount(1, $customers);
        $this->assertEquals($customerA->id, $customers->first()->id);
        $this->assertTrue($customers->every(fn ($c) => $c->tenant_id === $this->tenantA->id));
    }

    public function test_tenant_safe_raw_query_requires_tenant_context(): void
    {
        $this->createCustomersForBothTenants();

        app()->forgetInstance('current_tenant_id');

        $this->expectException(InvalidArgumentException::class);

        TenantSafeQuery::table('customers')->get();
    }

    public function test_belongstotenant_creating_event_auto_sets_tenant_id(): void
    {
        $this->actingAsTenantA();

        $customer = Customer::factory()->make()->toArray();
        unset($customer['tenant_id']);

        $created = Customer::create($customer);

        $this->assertNotNull($created->tenant_id);
        $this->assertEquals($this->tenantA->id, $created->tenant_id);
    }
}
