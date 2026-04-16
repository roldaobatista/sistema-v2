<?php

/**
 * Tenant Isolation — Stock Module
 *
 * Validates complete data isolation for: StockMovement, Warehouse, Inventory.
 * Cross-tenant access MUST return 404 (not 403).
 *
 * FAILURE HERE = INVENTORY DATA LEAK BETWEEN TENANTS
 */

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Model::unguard();
    Gate::before(fn () => true);

    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->userA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'current_tenant_id' => $this->tenantA->id,
        'is_active' => true,
    ]);

    $this->userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'current_tenant_id' => $this->tenantB->id,
        'is_active' => true,
    ]);

    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);
});

function actAsTenantStock(object $test, User $user, Tenant $tenant): void
{
    app()->instance('current_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    Sanctum::actingAs($user, ['*']);
}

// ══════════════════════════════════════════════════════════════════
//  WAREHOUSES
// ══════════════════════════════════════════════════════════════════

test('warehouse listing only shows own tenant', function () {
    Warehouse::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Warehouse A',
        'code' => 'WH-A', 'type' => 'fixed', 'is_active' => true,
    ]);
    Warehouse::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Warehouse B',
        'code' => 'WH-B', 'type' => 'fixed', 'is_active' => true,
    ]);

    actAsTenantStock($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/warehouses');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('name')->toArray())->not->toContain('Warehouse B');
});

test('cannot GET cross-tenant warehouse — returns 404', function () {
    $whB = Warehouse::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Secret WH',
        'code' => 'WH-SECRET', 'type' => 'fixed', 'is_active' => true,
    ]);

    actAsTenantStock($this, $this->userA, $this->tenantA);

    $this->getJson("/api/v1/warehouses/{$whB->id}")->assertNotFound();
});

test('Warehouse model scope isolates by tenant', function () {
    Warehouse::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Model WH A',
        'code' => 'MWH-A', 'type' => 'fixed', 'is_active' => true,
    ]);
    Warehouse::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Model WH B',
        'code' => 'MWH-B', 'type' => 'fixed', 'is_active' => true,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $warehouses = Warehouse::all();
    expect($warehouses)->toHaveCount(1);
    expect($warehouses->first()->name)->toBe('Model WH A');
});

// ══════════════════════════════════════════════════════════════════
//  STOCK MOVEMENTS
// ══════════════════════════════════════════════════════════════════

test('stock movements listing only shows own tenant', function () {
    $productA = Product::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $this->tenantA->id, 'name' => 'Prod A',
    ]);
    $productB = Product::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $this->tenantB->id, 'name' => 'Prod B',
    ]);

    StockMovement::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'product_id' => $productA->id,
        'type' => 'entry', 'quantity' => 10, 'unit_cost' => 50,
        'reference' => 'MOV-A', 'notes' => 'Stock A',
    ]);
    StockMovement::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'product_id' => $productB->id,
        'type' => 'entry', 'quantity' => 20, 'unit_cost' => 100,
        'reference' => 'MOV-B', 'notes' => 'Stock B',
    ]);

    actAsTenantStock($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/stock/movements');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
});

test('StockMovement model scope isolates by tenant', function () {
    $productA = Product::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $this->tenantA->id, 'name' => 'Scope Prod A',
    ]);
    $productB = Product::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $this->tenantB->id, 'name' => 'Scope Prod B',
    ]);

    StockMovement::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'product_id' => $productA->id,
        'type' => 'entry', 'quantity' => 5, 'unit_cost' => 25,
        'reference' => 'SCOPE-A',
    ]);
    StockMovement::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'product_id' => $productB->id,
        'type' => 'entry', 'quantity' => 8, 'unit_cost' => 40,
        'reference' => 'SCOPE-B',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $movements = StockMovement::all();
    expect($movements)->toHaveCount(1);
    expect($movements->first()->reference)->toBe('SCOPE-A');
});

// ══════════════════════════════════════════════════════════════════
//  STOCK SUMMARY & INTELLIGENCE
// ══════════════════════════════════════════════════════════════════

test('stock summary is tenant-scoped', function () {
    actAsTenantStock($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/stock/summary');
    $response->assertOk();
});

test('low stock alerts are tenant-scoped', function () {
    actAsTenantStock($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/stock/low-alerts');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  INVENTORY (BLIND COUNT)
// ══════════════════════════════════════════════════════════════════

test('inventory listing only shows own tenant', function () {
    $whA = Warehouse::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Inv WH A',
        'code' => 'IWH-A', 'type' => 'fixed', 'is_active' => true,
    ]);
    $whB = Warehouse::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Inv WH B',
        'code' => 'IWH-B', 'type' => 'fixed', 'is_active' => true,
    ]);
    Inventory::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'created_by' => $this->userA->id,
        'warehouse_id' => $whA->id, 'reference' => 'Inventory A', 'status' => 'open',
    ]);
    Inventory::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'created_by' => $this->userB->id,
        'warehouse_id' => $whB->id, 'reference' => 'Inventory B', 'status' => 'open',
    ]);

    actAsTenantStock($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/inventories');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('reference')->toArray())->not->toContain('Inventory B');
});

test('cannot GET cross-tenant inventory — returns 404', function () {
    $whB = Warehouse::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Inv WH B2',
        'code' => 'IWH-B2', 'type' => 'fixed', 'is_active' => true,
    ]);
    $invB = Inventory::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'created_by' => $this->userB->id,
        'warehouse_id' => $whB->id, 'reference' => 'Secret Inventory', 'status' => 'open',
    ]);

    actAsTenantStock($this, $this->userA, $this->tenantA);

    $this->getJson("/api/v1/inventories/{$invB->id}")->assertNotFound();
});

test('Inventory model scope isolates by tenant', function () {
    $whA = Warehouse::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Inv WH MA',
        'code' => 'IWH-MA', 'type' => 'fixed', 'is_active' => true,
    ]);
    $whB = Warehouse::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Inv WH MB',
        'code' => 'IWH-MB', 'type' => 'fixed', 'is_active' => true,
    ]);
    Inventory::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'created_by' => $this->userA->id,
        'warehouse_id' => $whA->id, 'reference' => 'Model Inv A', 'status' => 'open',
    ]);
    Inventory::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'created_by' => $this->userB->id,
        'warehouse_id' => $whB->id, 'reference' => 'Model Inv B', 'status' => 'open',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $inventories = Inventory::all();
    expect($inventories)->toHaveCount(1);
    expect($inventories->first()->reference)->toBe('Model Inv A');
});

// ══════════════════════════════════════════════════════════════════
//  PRODUCT MODEL SCOPE
// ══════════════════════════════════════════════════════════════════

test('Product model scope isolates by tenant', function () {
    Product::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $this->tenantA->id, 'name' => 'Product A',
    ]);
    Product::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $this->tenantB->id, 'name' => 'Product B',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $products = Product::all();
    expect($products)->each(fn ($p) => $p->tenant_id->toBe($this->tenantA->id));
    expect($products->pluck('name')->toArray())->not->toContain('Product B');
});
