<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\MaterialRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);
    Sanctum::actingAs($this->user, ['*']);
});

it('can list material requests with pagination', function () {
    MaterialRequest::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->user->id,
    ]);

    $response = $this->getJson('/api/v1/procurement/material-requests');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(3, 'data');
});

it('can create a material request', function () {
    $payload = [
        'reference' => 'MR-TEST-001',
        'status' => 'pending',
        'priority' => 'high',
        'justification' => 'Urgent need for materials',
    ];

    $response = $this->postJson('/api/v1/procurement/material-requests', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.reference', 'MR-TEST-001');

    $this->assertDatabaseHas('material_requests', [
        'reference' => 'MR-TEST-001',
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->user->id,
    ]);
});

it('can show a material request', function () {
    $mr = MaterialRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->user->id,
    ]);

    $response = $this->getJson("/api/v1/procurement/material-requests/{$mr->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $mr->id)
        ->assertJsonPath('data.reference', $mr->reference);
});

it('can update a material request', function () {
    $mr = MaterialRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->user->id,
    ]);

    $response = $this->putJson("/api/v1/procurement/material-requests/{$mr->id}", [
        'status' => 'approved',
        'priority' => 'urgent',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->assertDatabaseHas('material_requests', [
        'id' => $mr->id,
        'status' => 'approved',
        'priority' => 'urgent',
    ]);
});

it('can delete a material request', function () {
    $mr = MaterialRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->user->id,
    ]);

    $response = $this->deleteJson("/api/v1/procurement/material-requests/{$mr->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted('material_requests', ['id' => $mr->id]);
});

it('fails validation when required fields are missing on create', function () {
    $response = $this->postJson('/api/v1/procurement/material-requests', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['reference']);
});

it('fails validation when creating with invalid work_order_id', function () {
    $response = $this->postJson('/api/v1/procurement/material-requests', [
        'reference' => 'MR-INVALID',
        'work_order_id' => 99999,
        'status' => 'pending',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['work_order_id']);
});

it('fails validation with invalid warehouse_id', function () {
    $response = $this->postJson('/api/v1/procurement/material-requests', [
        'reference' => 'MR-INVALID-WH',
        'warehouse_id' => 99999,
        'status' => 'pending',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['warehouse_id']);
});

it('fails validation with unsupported status and priority', function () {
    $response = $this->postJson('/api/v1/procurement/material-requests', [
        'reference' => 'MR-INVALID-ENUM',
        'status' => 'draft',
        'priority' => 'critical',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'priority']);
});

it('cannot access material request from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherMr = MaterialRequest::factory()->create([
        'tenant_id' => $otherTenant->id,
        'requester_id' => $otherUser->id,
    ]);

    $response = $this->getJson("/api/v1/procurement/material-requests/{$otherMr->id}");

    $response->assertNotFound();
});

it('only lists material requests from own tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
    MaterialRequest::factory()->count(3)->create([
        'tenant_id' => $otherTenant->id,
        'requester_id' => $otherUser->id,
    ]);
    MaterialRequest::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->user->id,
    ]);

    $response = $this->getJson('/api/v1/procurement/material-requests');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('assigns tenant_id and requester_id automatically on create', function () {
    $payload = [
        'reference' => 'MR-AUTO-001',
        'status' => 'pending',
    ];

    $this->postJson('/api/v1/procurement/material-requests', $payload)->assertCreated();

    $this->assertDatabaseHas('material_requests', [
        'reference' => 'MR-AUTO-001',
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->user->id,
    ]);
});

it('respects per_page parameter with max limit of 100', function () {
    MaterialRequest::factory()->count(5)->create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->user->id,
    ]);

    $response = $this->getJson('/api/v1/procurement/material-requests?per_page=2');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2);
});

it('returns 403 Forbidden when accessing material request without permission', function () {
    $this->withMiddleware([CheckPermission::class]);
    Gate::before(fn () => false);

    $response = $this->getJson('/api/v1/procurement/material-requests');
    $response->assertForbidden();
});
