<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
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

test('soft deleted customer does not appear in listing', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Deleted Customer',
    ]);
    $activeCustomer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Active Customer',
    ]);

    $customer->delete();

    $response = $this->getJson('/api/v1/customers');

    $response->assertStatus(200);
    $names = collect($response->json('data'))->pluck('name')->toArray();
    expect($names)->toContain('Active Customer');
    expect($names)->not->toContain('Deleted Customer');
});

test('soft deleted payable does not appear in listing', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'description' => 'Deleted Payable',
    ]);
    $activePayable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'description' => 'Active Payable',
    ]);

    $payable->delete();

    $response = $this->getJson('/api/v1/accounts-payable');

    $response->assertStatus(200);
    $descriptions = collect($response->json('data'))->pluck('description')->toArray();
    expect($descriptions)->toContain('Active Payable');
    expect($descriptions)->not->toContain('Deleted Payable');
});

test('soft deleted customer still exists in database', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $customerId = $customer->id;

    $customer->delete();

    // Not found with normal query
    expect(Customer::find($customerId))->toBeNull();

    // Found with trashed
    expect(Customer::withTrashed()->find($customerId))->not->toBeNull();
    expect(Customer::withTrashed()->find($customerId)->trashed())->toBeTrue();
});

test('can create customer with same document after soft delete', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'PJ',
        'document' => '11222333000181', // Valid CNPJ
    ]);

    $customer->delete();

    // StoreCustomerRequest unique rule uses ->whereNull('deleted_at')
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PJ',
        'name' => 'New Customer Same Doc',
        'document' => '11222333000181',
    ]);

    $response->assertStatus(201);
});

test('soft deleted payable cannot be accessed via show endpoint', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $payable->delete();

    $response = $this->getJson("/api/v1/accounts-payable/{$payable->id}");

    // Route model binding should return 404 for soft deleted models
    $response->assertStatus(404);
});

test('soft deleted receivable cannot be accessed via show endpoint', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $receivable = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $receivable->delete();

    $response = $this->getJson("/api/v1/accounts-receivable/{$receivable->id}");

    $response->assertStatus(404);
});

test('payable with payments cannot be deleted', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 500.00,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    // Make a payment first
    $this->postJson("/api/v1/accounts-payable/{$payable->id}/pay", [
        'amount' => 100.00,
        'payment_method' => 'pix',
        'payment_date' => now()->toDateString(),
    ])->assertStatus(201);

    // Now try to delete
    $response = $this->deleteJson("/api/v1/accounts-payable/{$payable->id}");

    $response->assertStatus(409);
    expect($response->json('message'))->toContain('pagamentos vinculados');
});

test('receivable with payments cannot be deleted', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $receivable = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'amount' => 500.00,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
        'amount' => 100.00,
        'payment_method' => 'pix',
        'payment_date' => now()->toDateString(),
    ])->assertStatus(201);

    $response = $this->deleteJson("/api/v1/accounts-receivable/{$receivable->id}");

    $response->assertStatus(409);
});

test('payable without payments can be soft deleted', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 100.00,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $response = $this->deleteJson("/api/v1/accounts-payable/{$payable->id}");

    $response->assertStatus(204);
    expect(AccountPayable::find($payable->id))->toBeNull();
    expect(AccountPayable::withTrashed()->find($payable->id))->not->toBeNull();
});
