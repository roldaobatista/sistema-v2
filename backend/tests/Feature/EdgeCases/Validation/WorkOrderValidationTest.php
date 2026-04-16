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

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
});

test('customer_id is required for work order creation', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'description' => 'Test work order',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);
});

test('non-existent customer_id is rejected', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => 99999,
        'description' => 'Test work order',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);
});

test('cross-tenant customer_id is rejected', function () {
    $otherTenant = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $otherCustomer->id,
        'description' => 'Cross-tenant test',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);
});

test('description is required for work order creation', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

test('invalid priority value is rejected', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Test work order',
        'priority' => 'super_ultra_high',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['priority']);
});

test('valid priority values are accepted', function () {
    foreach (['low', 'normal', 'high', 'urgent'] as $priority) {
        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => "Priority {$priority} test",
            'priority' => $priority,
        ]);

        $response->assertStatus(201);
    }
});

test('scheduled_date must be a valid date', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Date test',
        'scheduled_date' => 'not-a-date',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['scheduled_date']);
});

test('item type must be product or service', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Item type test',
        'items' => [
            [
                'type' => 'invalid_type',
                'description' => 'Test item',
                'quantity' => 1,
                'unit_price' => 50.00,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.type']);
});

test('item description is required', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Item desc test',
        'items' => [
            [
                'type' => 'service',
                'quantity' => 1,
                'unit_price' => 50.00,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.description']);
});

test('cross-tenant assigned_to user is rejected', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Cross-tenant assigned_to',
        'assigned_to' => $otherUser->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['assigned_to']);
});

test('discount_percentage must be between 0 and 100', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Discount test',
        'discount_percentage' => 150,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['discount_percentage']);
});
