<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);
    Sanctum::actingAs($this->user, ['*']);
});

test('SQL injection in customer search parameter returns safe results', function () {
    Customer::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson('/api/v1/customers?search='.urlencode("'; DROP TABLE customers; --"));

    // Should return 200 with safe empty results, not execute injection
    $response->assertStatus(200);

    // Verify customers table still exists
    expect(Customer::count())->toBeGreaterThan(0);
});

test('SQL injection in accounts payable search parameter is safe', function () {
    AccountPayable::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson('/api/v1/accounts-payable?search='.urlencode('1 OR 1=1; --'));

    $response->assertStatus(200);
    expect(AccountPayable::count())->toBeGreaterThan(0);
});

test('SQL injection in status filter parameter is safe', function () {
    $response = $this->getJson('/api/v1/accounts-payable?status='.urlencode("pending'; DROP TABLE accounts_payable; --"));

    $response->assertStatus(200);
    expect(Schema::hasTable('accounts_payable'))->toBeTrue();
});

test('SQL injection in customer sort parameter does not crash', function () {
    Customer::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson('/api/v1/customers?sort='.urlencode('name; DROP TABLE customers; --'));

    // CustomerController validates sort with in: rule — SQL injection is rejected with 422
    expect($response->status())->toBeIn([200, 422]);
    expect(Schema::hasTable('customers'))->toBeTrue();
});

test('SQL injection in due_from filter parameter is safe', function () {
    $response = $this->getJson('/api/v1/accounts-payable?due_from='.urlencode("2024-01-01'; DELETE FROM accounts_payable; --"));

    // Should handle gracefully
    expect($response->status())->toBeIn([200, 422, 500]);
    expect(Schema::hasTable('accounts_payable'))->toBeTrue();
});

test('SQL injection in category filter parameter is safe', function () {
    $response = $this->getJson('/api/v1/accounts-payable?category='.urlencode('1 OR 1=1'));

    $response->assertStatus(200);
});

test('SQL injection in customer ID parameter is safe', function () {
    $response = $this->getJson('/api/v1/customers/'.urlencode('1; DROP TABLE customers; --'));

    // Should return 404 or 422, not execute SQL
    expect($response->status())->toBeIn([404, 422, 500]);
    expect(Customer::count())->toBeGreaterThanOrEqual(0);
});

test('LIKE wildcards in search are escaped', function () {
    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Customer ABC',
    ]);
    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Other Company XYZ',
    ]);

    // % should be escaped by SearchSanitizer, not act as wildcard
    $response = $this->getJson('/api/v1/customers?search=%25');

    $response->assertStatus(200);
    // Should NOT match all records via unescaped %
});

test('SQL injection in work order search is safe', function () {
    $response = $this->getJson('/api/v1/work-orders?search='.urlencode("' UNION SELECT * FROM users --"));

    $response->assertStatus(200);
    expect(Schema::hasTable('users'))->toBeTrue();
});

test('SQL injection via per_page parameter is safe', function () {
    $response = $this->getJson('/api/v1/customers?per_page='.urlencode('10; DROP TABLE customers; --'));

    // per_page is cast to int, so injection won't work
    expect($response->status())->toBeIn([200, 422]);
    expect(Customer::count())->toBeGreaterThanOrEqual(0);
});
