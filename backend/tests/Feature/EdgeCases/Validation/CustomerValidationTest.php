<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
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

test('name is required for customer creation', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('type is required for customer creation', function () {
    $response = $this->postJson('/api/v1/customers', [
        'name' => 'Test Customer',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

test('type must be PF or PJ', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'INVALID',
        'name' => 'Test Customer',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

test('invalid CPF format is rejected', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'document' => '00000000000', // All same digits - invalid CPF
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['document']);
});

test('invalid CNPJ format is rejected', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PJ',
        'name' => 'Test Company',
        'document' => '00000000000000', // All same digits - invalid CNPJ
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['document']);
});

test('valid CPF is accepted', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'document' => '52998224725', // Valid CPF
    ]);

    $response->assertStatus(201);
});

test('valid CNPJ is accepted', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PJ',
        'name' => 'Test Company',
        'document' => '11222333000181', // Valid CNPJ
    ]);

    $response->assertStatus(201);
});

test('invalid email format is rejected', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('valid email is accepted', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'email' => 'valid@email.com',
    ]);

    $response->assertStatus(201);
});

test('duplicate document CPF is rejected within same tenant', function () {
    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'document' => '52998224725',
    ]);

    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Another Customer',
        'document' => '52998224725',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['document']);
});

test('all required fields missing returns all validation errors', function () {
    $response = $this->postJson('/api/v1/customers', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['type', 'name']);
});

test('name max length 255 is enforced', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => str_repeat('A', 256),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('name at max length 255 is accepted', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => str_repeat('A', 255),
    ]);

    $response->assertStatus(201);
});

test('address_state max length 2 is enforced', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'address_state' => 'ABC',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['address_state']);
});

test('phone max length 20 is enforced', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'phone' => str_repeat('1', 21),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);
});

test('document with special characters is handled by CpfCnpj rule', function () {
    // CpfCnpj rule strips non-numeric characters
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'document' => '529.982.247-25', // Formatted valid CPF
    ]);

    $response->assertStatus(201);
});

test('contract_end must be after contract_start', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PJ',
        'name' => 'Test Company',
        'contract_start' => '2025-06-01',
        'contract_end' => '2025-01-01',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['contract_end']);
});
