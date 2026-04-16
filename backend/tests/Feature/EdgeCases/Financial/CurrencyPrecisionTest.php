<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountPayableCategory;
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

    $this->category = AccountPayableCategory::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
});

test('minimum value R$ 0.01 is accepted for payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'category_id' => $this->category->id,
        'description' => 'Minimum value test',
        'amount' => 0.01,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(201);
});

test('amount below minimum R$ 0.01 is rejected for payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'description' => 'Below minimum test',
        'amount' => 0.001,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    // 0.001 < 0.01 so it should fail validation min:0.01
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('minimum value R$ 0.01 is accepted for receivable', function () {
    $response = $this->postJson('/api/v1/accounts-receivable', [
        'customer_id' => $this->customer->id,
        'description' => 'Minimum value test',
        'amount' => 0.01,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(201);
});

test('very large value is accepted for payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'category_id' => $this->category->id,
        'description' => 'Large value test',
        'amount' => 1500000.00,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(201);
});

test('very large value is accepted for receivable', function () {
    $response = $this->postJson('/api/v1/accounts-receivable', [
        'customer_id' => $this->customer->id,
        'description' => 'Large value test',
        'amount' => 9999999.99,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(201);
});

test('installment rounding distributes cents correctly', function () {
    $response = $this->postJson('/api/v1/accounts-receivable/installments', [
        'customer_id' => $this->customer->id,
        'total_amount' => 1000.00,
        'description' => 'Installment test',
        'installments' => 3,
        'first_due_date' => now()->addDays(30)->toDateString(),
        'payment_method' => 'boleto',
    ]);

    $response->assertStatus(201);
    $installments = $response->json();

    // Sum of installments must equal total exactly
    $total = collect($installments)->sum('amount');
    expect(round($total, 2))->toBe(1000.00);

    // Two installments should be 333.33, last should be 333.34
    expect(count($installments))->toBe(3);
});

test('installment with odd division handles remainder', function () {
    $response = $this->postJson('/api/v1/accounts-receivable/installments', [
        'customer_id' => $this->customer->id,
        'total_amount' => 100.00,
        'description' => 'Odd split test',
        'installments' => 7,
        'first_due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(201);
    $installments = $response->json();

    $total = collect($installments)->sum('amount');
    expect(round($total, 2))->toBe(100.00);
    expect(count($installments))->toBe(7);
});

test('payment with exact remaining balance marks as paid', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 100.50,
        'amount_paid' => 50.25,
        'status' => 'partial',
    ]);

    $response = $this->postJson("/api/v1/accounts-payable/{$payable->id}/pay", [
        'amount' => 50.25,
        'payment_method' => 'pix',
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertStatus(201);

    $payable->refresh();
    expect((float) $payable->amount_paid)->toBe(100.50);
});
