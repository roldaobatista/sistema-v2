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

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
});

test('amount must be positive for account payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'description' => 'Test',
        'amount' => -10.00,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('amount is required for account payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'description' => 'Test',
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('due_date is required for account payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'description' => 'Test',
        'amount' => 100.00,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);
});

test('description is required for account payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'amount' => 100.00,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

test('description max length 255 is enforced for payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'description' => str_repeat('A', 256),
        'amount' => 100.00,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

test('payment_method max length 30 is enforced', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'description' => 'Test',
        'amount' => 100.00,
        'due_date' => now()->addDays(30)->toDateString(),
        'payment_method' => str_repeat('A', 31),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payment_method']);
});

test('customer_id is required for account receivable', function () {
    $response = $this->postJson('/api/v1/accounts-receivable', [
        'description' => 'Test receivable',
        'amount' => 100.00,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);
});

test('cross-tenant customer_id is rejected for receivable', function () {
    $otherTenant = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    $response = $this->postJson('/api/v1/accounts-receivable', [
        'customer_id' => $otherCustomer->id,
        'description' => 'Cross-tenant test',
        'amount' => 100.00,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);
});

test('payment requires amount, payment_method and payment_date', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 500.00,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $response = $this->postJson("/api/v1/accounts-payable/{$payable->id}/pay", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount', 'payment_method', 'payment_date']);
});

test('payment_method is required for payment', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 500.00,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $response = $this->postJson("/api/v1/accounts-payable/{$payable->id}/pay", [
        'amount' => 100.00,
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payment_method']);
});

test('all required fields missing returns all validation errors for payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['category_id', 'description', 'amount', 'due_date']);
});
