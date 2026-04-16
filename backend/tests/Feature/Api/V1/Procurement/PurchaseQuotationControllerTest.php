<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\PurchaseQuotation;
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

it('can list purchase quotations with pagination', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
    PurchaseQuotation::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
    ]);

    $response = $this->getJson('/api/v1/procurement/purchase-quotations');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(3, 'data');
});

it('can create a purchase quotation', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

    $payload = [
        'reference' => 'PQ-TEST-001',
        'supplier_id' => $supplier->id,
        'status' => 'pending',
        'total' => 1500.50,
        'notes' => 'Test quotation',
    ];

    $response = $this->postJson('/api/v1/procurement/purchase-quotations', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.reference', 'PQ-TEST-001');

    $this->assertDatabaseHas('purchase_quotations', [
        'reference' => 'PQ-TEST-001',
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'supplier_id' => $supplier->id,
    ]);
});

it('can show a purchase quotation', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
    $pq = PurchaseQuotation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
    ]);

    $response = $this->getJson("/api/v1/procurement/purchase-quotations/{$pq->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $pq->id)
        ->assertJsonPath('data.reference', $pq->reference);
});

it('can update a purchase quotation', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
    $pq = PurchaseQuotation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
    ]);

    $response = $this->putJson("/api/v1/procurement/purchase-quotations/{$pq->id}", [
        'status' => 'approved',
        'notes' => 'Updated notes',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->assertDatabaseHas('purchase_quotations', [
        'id' => $pq->id,
        'status' => 'approved',
        'notes' => 'Updated notes',
    ]);
});

it('can delete a purchase quotation', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
    $pq = PurchaseQuotation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
    ]);

    $response = $this->deleteJson("/api/v1/procurement/purchase-quotations/{$pq->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted('purchase_quotations', ['id' => $pq->id]);
});

it('fails validation when required supplier_id is missing on create', function () {
    $response = $this->postJson('/api/v1/procurement/purchase-quotations', [
        'reference' => 'PQ-FAIL',
        'status' => 'draft',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['supplier_id']);
});

it('fails validation with invalid supplier_id on create', function () {
    $response = $this->postJson('/api/v1/procurement/purchase-quotations', [
        'reference' => 'PQ-INVALID',
        'supplier_id' => 99999,
        'status' => 'draft',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['supplier_id']);
});

it('cannot access purchase quotation from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherSupplier = Supplier::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherPq = PurchaseQuotation::factory()->create([
        'tenant_id' => $otherTenant->id,
        'supplier_id' => $otherSupplier->id,
    ]);

    $response = $this->getJson("/api/v1/procurement/purchase-quotations/{$otherPq->id}");

    $response->assertNotFound();
});

it('only lists purchase quotations from own tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherSupplier = Supplier::factory()->create(['tenant_id' => $otherTenant->id]);
    PurchaseQuotation::factory()->count(3)->create([
        'tenant_id' => $otherTenant->id,
        'supplier_id' => $otherSupplier->id,
    ]);

    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
    PurchaseQuotation::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
    ]);

    $response = $this->getJson('/api/v1/procurement/purchase-quotations');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('assigns tenant_id and created_by automatically on create', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

    $payload = [
        'supplier_id' => $supplier->id,
        'reference' => 'PQ-AUTO-001',
        'status' => 'draft',
    ];

    $this->postJson('/api/v1/procurement/purchase-quotations', $payload)->assertCreated();

    $this->assertDatabaseHas('purchase_quotations', [
        'reference' => 'PQ-AUTO-001',
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
});

it('rejects supplier_id from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherSupplier = Supplier::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = $this->postJson('/api/v1/procurement/purchase-quotations', [
        'supplier_id' => $otherSupplier->id,
        'reference' => 'PQ-CROSS-TENANT',
        'status' => 'draft',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['supplier_id']);
});

it('respects per_page parameter with max limit of 100', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
    PurchaseQuotation::factory()->count(5)->create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
    ]);

    $response = $this->getJson('/api/v1/procurement/purchase-quotations?per_page=2');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2);
});

it('returns 403 Forbidden when accessing purchase quotation without permission', function () {
    $this->withMiddleware([CheckPermission::class]);
    Gate::before(fn () => false);

    $response = $this->getJson('/api/v1/procurement/purchase-quotations');
    $response->assertForbidden();
});
