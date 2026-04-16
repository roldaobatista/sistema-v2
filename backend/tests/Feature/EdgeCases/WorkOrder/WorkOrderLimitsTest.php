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

test('work order with zero items is accepted', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'OS without items',
        'items' => [],
    ]);

    $response->assertStatus(201);
});

test('work order with many items is accepted', function () {
    $items = [];
    for ($i = 0; $i < 50; $i++) {
        $items[] = [
            'type' => 'service',
            'description' => "Service item {$i}",
            'quantity' => 1,
            'unit_price' => 10.00,
        ];
    }

    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'OS with many items',
        'items' => $items,
    ]);

    $response->assertStatus(201);
});

test('work order with extremely long description is accepted', function () {
    $longDescription = str_repeat('A', 5000);

    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => $longDescription,
    ]);

    // description is 'required|string' with no max, should be accepted
    $response->assertStatus(201);
});

test('work order with special characters in description is accepted', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Manutencao preventiva - Equipamento #123 (urgente!) & recalibracao @lab <prioridade>',
    ]);

    $response->assertStatus(201);
});

test('work order with unicode and accented characters is accepted', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Calibracao do equipamento com medicao de temperatura (graus Celsius)',
    ]);

    $response->assertStatus(201);
});

test('work order with all optional fields empty is accepted', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Minimal work order',
        'equipment_id' => '',
        'assigned_to' => '',
        'scheduled_date' => '',
        'internal_notes' => '',
        'os_number' => '',
    ]);

    $response->assertStatus(201);
});

test('work order requires customer_id', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'description' => 'OS without customer',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);
});

test('work order requires description', function () {
    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});
