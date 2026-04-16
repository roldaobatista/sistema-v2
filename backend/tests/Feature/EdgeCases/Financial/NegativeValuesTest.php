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

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
});

test('negative amount in account payable is rejected', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'description' => 'Test negative',
        'amount' => -100.00,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('zero amount in account payable is rejected', function () {
    $response = $this->postJson('/api/v1/accounts-payable', [
        'description' => 'Test zero',
        'amount' => 0,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('negative amount in account receivable is rejected', function () {
    $response = $this->postJson('/api/v1/accounts-receivable', [
        'customer_id' => $this->customer->id,
        'description' => 'Test negative receivable',
        'amount' => -500.00,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('negative payment amount is rejected', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 1000.00,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $response = $this->postJson("/api/v1/accounts-payable/{$payable->id}/pay", [
        'amount' => -50.00,
        'payment_method' => 'pix',
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('overpayment exceeding remaining balance is rejected for payable', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 100.00,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $response = $this->postJson("/api/v1/accounts-payable/{$payable->id}/pay", [
        'amount' => 150.00,
        'payment_method' => 'pix',
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('excede o saldo restante');
});

test('overpayment exceeding remaining balance is rejected for receivable', function () {
    $receivable = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 200.00,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $response = $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
        'amount' => 250.00,
        'payment_method' => 'pix',
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('excede o saldo restante');
});

test('payment on fully paid payable is rejected', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 100.00,
        'amount_paid' => 100.00,
        'status' => 'paid',
    ]);

    $response = $this->postJson("/api/v1/accounts-payable/{$payable->id}/pay", [
        'amount' => 10.00,
        'payment_method' => 'pix',
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
});

test('payment on cancelled payable is rejected', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 500.00,
        'amount_paid' => 0,
        'status' => 'cancelled',
    ]);

    $response = $this->postJson("/api/v1/accounts-payable/{$payable->id}/pay", [
        'amount' => 100.00,
        'payment_method' => 'pix',
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
});

test('payment on cancelled receivable is rejected', function () {
    $receivable = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 500.00,
        'amount_paid' => 0,
        'status' => 'cancelled',
    ]);

    $response = $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
        'amount' => 100.00,
        'payment_method' => 'pix',
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
});

test('cannot update paid payable', function () {
    $payable = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 100.00,
        'amount_paid' => 100.00,
        'status' => 'paid',
    ]);

    $response = $this->putJson("/api/v1/accounts-payable/{$payable->id}", [
        'description' => 'Updated description',
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('cancelado ou pago');
});
