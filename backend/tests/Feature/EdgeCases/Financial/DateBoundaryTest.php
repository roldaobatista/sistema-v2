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

test('due date in the past is accepted for payable', function () {
    // StoreAccountPayableRequest only requires 'date', not 'after:today'
    $response = $this->postJson('/api/v1/accounts-payable', [
        'category_id' => $this->category->id,
        'description' => 'Past due date',
        'amount' => 100.00,
        'due_date' => now()->subDays(30)->toDateString(),
    ]);

    $response->assertStatus(201);
});

test('due date far in the future is accepted for payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'category_id' => $this->category->id,
        'description' => 'Future due date',
        'amount' => 200.00,
        'due_date' => '2030-12-31',
    ]);

    $response->assertStatus(201);
});

test('leap year date Feb 29 is accepted as due date', function () {
    // 2028 is a leap year
    $response = $this->postJson('/api/v1/accounts-payable', [
        'category_id' => $this->category->id,
        'description' => 'Leap year test',
        'amount' => 150.00,
        'due_date' => '2028-02-29',
    ]);

    $response->assertStatus(201);
});

test('invalid date format is rejected for payable', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'description' => 'Invalid date',
        'amount' => 100.00,
        'due_date' => 'not-a-date',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);
});

test('year boundary dates work correctly for payment', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 500.00,
        'amount_paid' => 0,
        'status' => 'pending',
        'due_date' => '2026-12-31',
    ]);

    $response = $this->postJson("/api/v1/accounts-payable/{$payable->id}/pay", [
        'amount' => 500.00,
        'payment_method' => 'pix',
        'payment_date' => '2027-01-01',
    ]);

    $response->assertStatus(201);
});

test('installment first_due_date in the past is rejected', function () {
    $response = $this->postJson('/api/v1/accounts-receivable/installments', [
        'customer_id' => $this->customer->id,
        'total_amount' => 1000.00,
        'description' => 'Past date installment',
        'installments' => 3,
        'first_due_date' => now()->subDays(10)->toDateString(),
    ]);

    // GenerateReceivableInstallmentsRequest has 'after_or_equal:today'
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['first_due_date']);
});
