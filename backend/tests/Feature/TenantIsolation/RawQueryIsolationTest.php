<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

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

    public function test_raw_query_with_tenant_filter_respects_isolation(): void
    {
        [$customerA, $customerB] = $this->createCustomersForBothTenants();

        $this->actingAsTenantA();

        $customers = DB::table('customers')
            ->where('tenant_id', app('current_tenant_id'))
            ->get();

        $this->assertCount(1, $customers);
        $this->assertEquals($customerA->id, $customers->first()->id);
        $this->assertTrue($customers->every(fn ($c) => $c->tenant_id === $this->tenantA->id));
    }

    public function test_raw_query_without_tenant_filter_leaks_data(): void
    {
        [$customerA, $customerB] = $this->createCustomersForBothTenants();

        $this->actingAsTenantA();

        $customers = DB::table('customers')->get();

        // Raw queries without tenant filter return ALL tenants' data — this documents the risk.
        $tenantIds = $customers->pluck('tenant_id')->unique()->sort()->values();

        $this->assertTrue(
            $tenantIds->count() >= 2,
            'Raw DB::table() without tenant filter must return data from multiple tenants, proving global scope bypass.'
        );
        $this->assertTrue($customers->contains('id', $customerA->id));
        $this->assertTrue($customers->contains('id', $customerB->id));
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
