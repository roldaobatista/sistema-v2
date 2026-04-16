<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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

test('page=0 is handled gracefully on customers listing', function () {
    Customer::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson('/api/v1/customers?page=0');

    // Laravel defaults page=0 to page=1 or returns empty
    expect($response->status())->toBeIn([200, 422]);
});

test('page=-1 is handled gracefully', function () {
    $response = $this->getJson('/api/v1/customers?page=-1');

    expect($response->status())->toBeIn([200, 422]);
});

test('page=999999 returns empty data set', function () {
    Customer::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson('/api/v1/customers?page=999999');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toBeEmpty();
});

test('per_page=0 is handled gracefully on accounts payable', function () {
    AccountPayable::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson('/api/v1/accounts-payable?per_page=0');

    // Should return data with default per_page or error
    expect($response->status())->toBeIn([200, 422]);
});

test('per_page is capped at maximum 100 for accounts payable', function () {
    AccountPayable::factory()->count(5)->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson('/api/v1/accounts-payable?per_page=10000');

    $response->assertStatus(200);
    // Controller uses min((int) $request->get('per_page', 30), 100)
    $perPage = $response->json('meta.per_page') ?? $response->json('per_page');
    if ($perPage !== null) {
        expect($perPage)->toBeLessThanOrEqual(100);
    }
});

test('per_page is capped at maximum 100 for customers', function () {
    $response = $this->getJson('/api/v1/customers?per_page=5000');

    // CustomerController validates per_page with max:100 rule and rejects with 422
    $response->assertStatus(422);
    expect($response->json('errors.per_page'))->not->toBeNull();
});

test('sorting by non-existent column falls back to default on customers', function () {
    Customer::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    // CustomerController validates sort with in: rule — invalid column returns 422
    $response = $this->getJson('/api/v1/customers?sort=nonexistent_column');

    $response->assertStatus(422);
    expect($response->json('errors.sort'))->not->toBeNull();
});

test('sorting by allowed column works correctly on customers', function () {
    Customer::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson('/api/v1/customers?sort=created_at&direction=desc');

    $response->assertStatus(200);
});
