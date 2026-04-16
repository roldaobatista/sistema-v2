<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Supplier;
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

it('can list suppliers with pagination', function () {
    Supplier::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/v1/procurement/suppliers');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(3, 'data');
});

it('can create a supplier', function () {
    $payload = [
        'type' => 'PJ',
        'name' => 'Test Supplier Ltd',
        'document' => '12.345.678/0001-90',
        'email' => 'contact@test-supplier.com',
        'phone' => '11999998888',
    ];

    $response = $this->postJson('/api/v1/procurement/suppliers', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Test Supplier Ltd');

    $this->assertDatabaseHas('suppliers', [
        'name' => 'Test Supplier Ltd',
        'tenant_id' => $this->tenant->id,
    ]);
});

it('can show a supplier', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/v1/procurement/suppliers/{$supplier->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $supplier->id)
        ->assertJsonPath('data.name', $supplier->name);
});

it('can update a supplier', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->putJson("/api/v1/procurement/suppliers/{$supplier->id}", [
        'name' => 'Updated Supplier Name',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Supplier Name');

    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'name' => 'Updated Supplier Name',
    ]);
});

it('can delete a supplier', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->deleteJson("/api/v1/procurement/suppliers/{$supplier->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
});

it('fails validation when required fields are missing on create', function () {
    $response = $this->postJson('/api/v1/procurement/suppliers', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'name']);
});

it('fails validation with invalid data on create', function () {
    $response = $this->postJson('/api/v1/procurement/suppliers', [
        'type' => 'INVALID_TYPE',
        'name' => '',
        'email' => 'not-an-email',
    ]);

    $response->assertUnprocessable();
});

it('cannot access supplier from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherSupplier = Supplier::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = $this->getJson("/api/v1/procurement/suppliers/{$otherSupplier->id}");

    $response->assertNotFound();
});

it('only lists suppliers from own tenant', function () {
    $otherTenant = Tenant::factory()->create();
    Supplier::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);
    Supplier::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/v1/procurement/suppliers');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('assigns tenant_id automatically on create', function () {
    $payload = [
        'type' => 'PF',
        'name' => 'Auto Tenant Supplier',
    ];

    $this->postJson('/api/v1/procurement/suppliers', $payload)->assertCreated();

    $this->assertDatabaseHas('suppliers', [
        'name' => 'Auto Tenant Supplier',
        'tenant_id' => $this->tenant->id,
    ]);
});

it('respects per_page parameter with max limit of 100', function () {
    Supplier::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/v1/procurement/suppliers?per_page=2');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2);
});

it('returns 403 Forbidden when accessing supplier without permission', function () {
    $this->withMiddleware([CheckPermission::class]);
    Gate::before(fn () => false);

    $response = $this->getJson('/api/v1/procurement/suppliers');
    $response->assertForbidden();
});
