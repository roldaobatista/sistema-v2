<?php

/**
 * Tenant Isolation — Customer Module
 *
 * Validates complete data isolation between tenants for the Customer entity.
 * Cross-tenant access MUST return 404 (not 403) to prevent information disclosure.
 *
 * FAILURE HERE = CRITICAL SECURITY BREACH
 */

use App\Models\Customer;
use App\Models\Lookups\LeadSource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Model::unguard();

    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->userA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'current_tenant_id' => $this->tenantA->id,
        'is_active' => true,
    ]);

    $this->userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'current_tenant_id' => $this->tenantB->id,
        'is_active' => true,
    ]);

    foreach ([[$this->userA, $this->tenantA], [$this->userB, $this->tenantB]] as [$user, $tenant]) {
        $user->tenants()->syncWithoutDetaching([$tenant->id => ['is_default' => true]]);
        app()->instance('current_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        $user->assignRole('super_admin');
    }
});

// ─── Helper: act as tenant ────────────────────────────────────────
function actAsTenantCustomer(object $test, User $user, Tenant $tenant): void
{
    app()->instance('current_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    Sanctum::actingAs($user, ['*']);
}

// ─── 1. Listing only shows own tenant customers ───────────────────
test('customer listing only returns own tenant data', function () {
    // Create customers bypassing scope
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Client A1', 'type' => 'PJ']);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Client A2', 'type' => 'PF']);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Client B1', 'type' => 'PJ']);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Client B2', 'type' => 'PF']);

    actAsTenantCustomer($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/customers');

    $response->assertOk();
    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('name')->toArray())->not->toContain('Client B1');
    expect($data->pluck('name')->toArray())->not->toContain('Client B2');
});

// ─── 2. Cannot GET cross-tenant customer (404, not 403) ──────────
test('tenant A cannot GET tenant B customer by ID — returns 404', function () {
    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Secret Client B',
        'type' => 'PJ',
    ]);

    actAsTenantCustomer($this, $this->userA, $this->tenantA);

    $response = $this->getJson("/api/v1/customers/{$customerB->id}");

    $response->assertNotFound();
});

// ─── 3. Cannot UPDATE cross-tenant customer ───────────────────────
test('tenant A cannot UPDATE tenant B customer — returns 404', function () {
    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Secret Client B',
        'type' => 'PJ',
    ]);

    actAsTenantCustomer($this, $this->userA, $this->tenantA);

    $response = $this->putJson("/api/v1/customers/{$customerB->id}", [
        'name' => 'Hacked Name',
    ]);

    $response->assertNotFound();

    // Verify database was not modified
    $this->assertDatabaseHas('customers', [
        'id' => $customerB->id,
        'name' => 'Secret Client B',
    ]);
});

// ─── 4. Cannot DELETE cross-tenant customer ───────────────────────
test('tenant A cannot DELETE tenant B customer — returns 404', function () {
    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Protected Client B',
        'type' => 'PJ',
    ]);

    actAsTenantCustomer($this, $this->userA, $this->tenantA);

    $response = $this->deleteJson("/api/v1/customers/{$customerB->id}");

    $response->assertNotFound();

    // Verify record still exists
    $this->assertDatabaseHas('customers', [
        'id' => $customerB->id,
        'deleted_at' => null,
    ]);
});

// ─── 5. Created customer gets current user tenant_id ──────────────
test('creating customer assigns correct tenant_id from authenticated user', function () {
    actAsTenantCustomer($this, $this->userA, $this->tenantA);

    $response = $this->postJson('/api/v1/customers', [
        'name' => 'New Client Via API',
        'type' => 'PF',
        'document' => '529.982.247-25',
    ]);

    $response->assertCreated();

    $customerId = $response->json('data.id') ?? $response->json('id');
    $customer = Customer::withoutGlobalScopes()->find($customerId);

    expect($customer)->not->toBeNull();
    expect($customer->tenant_id)->toBe($this->tenantA->id);
});

// ─── 6. Model scope filters at query level ────────────────────────
test('Customer::all() respects global tenant scope', function () {
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Scope A', 'type' => 'PF']);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Scope B', 'type' => 'PF']);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $customers = Customer::all();

    expect($customers)->each(fn ($c) => $c->tenant_id->toBe($this->tenantA->id));
    expect($customers->pluck('name')->toArray())->not->toContain('Scope B');
});

// ─── 7. Customer::find() returns null for cross-tenant ────────────
test('Customer::find() returns null for cross-tenant record', function () {
    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Invisible',
        'type' => 'PJ',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    expect(Customer::find($customerB->id))->toBeNull();
});

// ─── 8. Customer count only includes own tenant ───────────────────
test('customer count only reflects own tenant records', function () {
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'A1', 'type' => 'PF']);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'A2', 'type' => 'PF']);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'B1', 'type' => 'PF']);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'B2', 'type' => 'PF']);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'B3', 'type' => 'PF']);

    app()->instance('current_tenant_id', $this->tenantA->id);
    expect(Customer::count())->toBe(2);

    app()->instance('current_tenant_id', $this->tenantB->id);
    expect(Customer::count())->toBe(3);
});

// ─── 9. Search/filter only returns own tenant ─────────────────────
test('customer search API only returns own tenant results', function () {
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Acme Corp A', 'type' => 'PJ']);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Acme Corp B', 'type' => 'PJ']);

    actAsTenantCustomer($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/customers?search=Acme');

    $response->assertOk();
    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('name')->toArray())->not->toContain('Acme Corp B');
});

// ─── 10. Customer options endpoint is tenant-scoped ───────────────
test('customer options endpoint only returns own tenant', function () {
    // The /customers/options endpoint returns lookup metadata (sources, segments, etc.)
    // scoped by tenant via Lookup models. Create tenant-specific lookups to verify isolation.
    LeadSource::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Source A', 'slug' => 'source-a', 'is_active' => true, 'sort_order' => 0,
    ]);
    LeadSource::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Source B', 'slug' => 'source-b', 'is_active' => true, 'sort_order' => 0,
    ]);

    actAsTenantCustomer($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/customers/options');

    $response->assertOk();
    $json = $response->json('data');

    // sources should contain tenant A's lookup, not tenant B's
    $sourceValues = is_array($json['sources']) ? array_values($json['sources']) : [];
    expect($sourceValues)->toContain('Source A');
    expect($sourceValues)->not->toContain('Source B');
});

// ─── 11. Switching to tenant B shows tenant B data ────────────────
test('switching tenant context correctly swaps visible data', function () {
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Switch A', 'type' => 'PF']);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Switch B', 'type' => 'PF']);

    // As tenant A
    actAsTenantCustomer($this, $this->userA, $this->tenantA);
    $responseA = $this->getJson('/api/v1/customers');
    $responseA->assertOk();
    $namesA = collect($responseA->json('data'))->pluck('name')->toArray();
    expect($namesA)->toContain('Switch A');
    expect($namesA)->not->toContain('Switch B');

    // As tenant B
    actAsTenantCustomer($this, $this->userB, $this->tenantB);
    $responseB = $this->getJson('/api/v1/customers');
    $responseB->assertOk();
    $namesB = collect($responseB->json('data'))->pluck('name')->toArray();
    expect($namesB)->toContain('Switch B');
    expect($namesB)->not->toContain('Switch A');
});

// ─── 12. Bulk operations respect tenant scope ─────────────────────
test('customer relationships (contacts, deals) respect tenant scope', function () {
    $customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Relational A',
        'type' => 'PJ',
    ]);
    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Relational B',
        'type' => 'PJ',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    // Only tenant A's customer should be loadable
    $loaded = Customer::with('contacts', 'deals')->find($customerA->id);
    expect($loaded)->not->toBeNull();

    $loadedB = Customer::with('contacts', 'deals')->find($customerB->id);
    expect($loadedB)->toBeNull();
});

// ─── 13. Cannot access cross-tenant via direct DB scope ───────────
test('direct where clause with cross-tenant ID returns empty', function () {
    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Direct Access B',
        'type' => 'PJ',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $result = Customer::where('id', $customerB->id)->first();
    expect($result)->toBeNull();
});

// ─── 14. Database integrity — tenant_id is immutable via API ──────
test('cannot change tenant_id of existing customer via update', function () {
    $customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Immutable Tenant',
        'type' => 'PJ',
    ]);

    actAsTenantCustomer($this, $this->userA, $this->tenantA);

    $this->putJson("/api/v1/customers/{$customerA->id}", [
        'tenant_id' => $this->tenantB->id,
        'name' => 'Updated Name',
    ]);

    $fresh = Customer::withoutGlobalScopes()->find($customerA->id);
    expect($fresh->tenant_id)->toBe($this->tenantA->id);
});

// ─── 15. Mass query never leaks cross-tenant ──────────────────────
test('whereIn query with mixed tenant IDs only returns own', function () {
    $a1 = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Mass A1', 'type' => 'PF']);
    $b1 = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Mass B1', 'type' => 'PF']);
    $a2 = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Mass A2', 'type' => 'PF']);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $results = Customer::whereIn('id', [$a1->id, $b1->id, $a2->id])->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('id')->toArray())->toContain($a1->id, $a2->id);
    expect($results->pluck('id')->toArray())->not->toContain($b1->id);
});
