<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
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

function createWorkOrder($tenant, $user, $customer, string $status): WorkOrder
{
    return WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
        'status' => $status,
    ]);
}

test('cannot transition from CANCELLED to IN_SERVICE', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_CANCELLED);

    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_IN_SERVICE,
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('Transição inválida');
});

test('cannot transition from INVOICED to any status (terminal state)', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_INVOICED);

    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_OPEN,
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('Transição inválida');
});

test('cannot transition from INVOICED to COMPLETED', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_INVOICED);

    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_COMPLETED,
    ]);

    $response->assertStatus(422);
});

test('cannot skip from OPEN directly to INVOICED', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_OPEN);

    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_INVOICED,
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('Transição inválida');
});

test('cannot skip from OPEN directly to COMPLETED', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_OPEN);

    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_COMPLETED,
    ]);

    $response->assertStatus(422);
});

test('cannot skip from OPEN directly to DELIVERED', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_OPEN);

    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_DELIVERED,
    ]);

    $response->assertStatus(422);
});

test('CANCELLED can only transition to OPEN', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_CANCELLED);

    // Should succeed
    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_OPEN,
    ]);

    $response->assertSuccessful();
});

test('DELIVERED can only transition to INVOICED', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_DELIVERED);

    // Attempt invalid transition
    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_COMPLETED,
    ]);

    $response->assertStatus(422);
});

test('DISPLACEMENT_PAUSED can only go back to IN_DISPLACEMENT', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_DISPLACEMENT_PAUSED);

    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_COMPLETED,
    ]);

    $response->assertStatus(422);
});

test('SERVICE_PAUSED can only go back to IN_SERVICE', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_SERVICE_PAUSED);

    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_OPEN,
    ]);

    $response->assertStatus(422);
});

test('invalid status value is rejected', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_OPEN);

    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => 'nonexistent_status',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('DELIVERED to INVOICED requires agreed_payment_method', function () {
    $wo = createWorkOrder($this->tenant, $this->user, $this->customer, WorkOrder::STATUS_DELIVERED);

    $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrder::STATUS_INVOICED,
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('forma de pagamento');
});
