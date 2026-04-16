<?php

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureTenantScope::class,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
});

function stockUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'current_tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    setPermissionsTeamId($tenant->id);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $user->givePermissionTo($perm);
    }

    return $user;
}

// ============================================================
// Stock Movements - View
// ============================================================

test('user WITH estoque.movement.view can list stock movements', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/movements')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on list stock movements', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/movements')->assertForbidden();
});

test('user WITH estoque.movement.view can access stock summary', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/summary')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on stock summary', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/summary')->assertForbidden();
});

test('user WITH estoque.movement.view can access low stock alerts', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/low-alerts')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on low stock alerts', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/low-alerts')->assertForbidden();
});

// ============================================================
// Stock Movements - Create
// ============================================================

test('user WITH estoque.movement.create can store stock movement', function () {
    $user = stockUser($this->tenant, ['estoque.movement.create']);
    Sanctum::actingAs($user, ['*']);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->postJson('/api/v1/stock/movements', [
        'type' => 'entry',
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'notes' => 'Entrada de materiais',
    ])->assertSuccessful();
});

test('user WITHOUT estoque.movement.create gets 403 on store stock movement', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/stock/movements', [
        'type' => 'entry',
        'quantity' => 10,
    ])->assertForbidden();
});

// ============================================================
// Inventories
// ============================================================

test('user WITH estoque.movement.view can list inventories', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view', 'estoque.inventory.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/inventories')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on list inventories', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/inventories')->assertForbidden();
});

test('user WITH estoque.movement.view can show an inventory', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view', 'estoque.inventory.view']);
    Sanctum::actingAs($user, ['*']);

    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $inventory = Inventory::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->getJson("/api/v1/inventories/{$inventory->id}")->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on show inventory', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $inventory = Inventory::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->getJson("/api/v1/inventories/{$inventory->id}")->assertForbidden();
});

test('user WITH estoque.manage can create inventory', function () {
    $user = stockUser($this->tenant, ['estoque.manage', 'estoque.movement.view', 'estoque.inventory.create']);
    Sanctum::actingAs($user, ['*']);

    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->postJson('/api/v1/inventories', [
        'warehouse_id' => $warehouse->id,
    ])->assertStatus(201);
});

test('user WITHOUT estoque.manage gets 403 on create inventory', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventories', [
        'warehouse_id' => 1,
    ])->assertForbidden();
});

test('user WITH estoque.manage can complete inventory', function () {
    $user = stockUser($this->tenant, ['estoque.manage', 'estoque.movement.view', 'estoque.inventory.create']);
    Sanctum::actingAs($user, ['*']);

    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $inventory = Inventory::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->postJson("/api/v1/inventories/{$inventory->id}/complete")->assertOk();
});

test('user WITHOUT estoque.manage gets 403 on complete inventory', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $inventory = Inventory::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->postJson("/api/v1/inventories/{$inventory->id}/complete")->assertForbidden();
});

test('user WITH estoque.manage can cancel inventory', function () {
    $user = stockUser($this->tenant, ['estoque.manage', 'estoque.movement.view', 'estoque.inventory.create']);
    Sanctum::actingAs($user, ['*']);

    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $inventory = Inventory::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->postJson("/api/v1/inventories/{$inventory->id}/cancel")->assertOk();
});

test('user WITHOUT estoque.manage gets 403 on cancel inventory', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $inventory = Inventory::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->postJson("/api/v1/inventories/{$inventory->id}/cancel")->assertForbidden();
});

// ============================================================
// Stock Transfers
// ============================================================

test('user WITH estoque.view can list stock transfers', function () {
    $user = stockUser($this->tenant, ['estoque.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/transfers')->assertOk();
});

test('user WITHOUT estoque.view gets 403 on list stock transfers', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/transfers')->assertForbidden();
});

test('user WITH estoque.view can show stock transfer', function () {
    $user = stockUser($this->tenant, ['estoque.view']);
    Sanctum::actingAs($user, ['*']);

    $fromWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
    $toWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $transfer = StockTransfer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
    ]);

    $this->getJson("/api/v1/stock/transfers/{$transfer->id}")->assertOk();
});

test('user WITHOUT estoque.view gets 403 on show stock transfer', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $fromWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
    $toWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $transfer = StockTransfer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
    ]);

    $this->getJson("/api/v1/stock/transfers/{$transfer->id}")->assertForbidden();
});

test('user WITH estoque.transfer.create can store stock transfer', function () {
    $user = stockUser($this->tenant, ['estoque.view', 'estoque.transfer.create']);
    Sanctum::actingAs($user, ['*']);

    $fromWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
    $toWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    // Ensure the source warehouse has enough stock for the transfer
    WarehouseStock::create([
        'warehouse_id' => $fromWarehouse->id,
        'product_id' => $product->id,
        'quantity' => 100,
    ]);

    $this->postJson('/api/v1/stock/transfers', [
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'notes' => 'Transferencia entre armazens',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ])->assertSuccessful();
});

test('user WITHOUT estoque.transfer.create gets 403 on store stock transfer', function () {
    $user = stockUser($this->tenant, ['estoque.view']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/stock/transfers', [
        'notes' => 'Transferencia entre armazens',
    ])->assertForbidden();
});

test('user WITH estoque.transfer.accept can accept stock transfer', function () {
    $user = stockUser($this->tenant, ['estoque.view', 'estoque.transfer.accept']);
    Sanctum::actingAs($user, ['*']);

    $fromWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
    $toWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $transfer = StockTransfer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'to_user_id' => $user->id,
    ]);

    $this->postJson("/api/v1/stock/transfers/{$transfer->id}/accept")->assertOk();
});

test('user WITHOUT estoque.transfer.accept gets 403 on accept stock transfer', function () {
    $user = stockUser($this->tenant, ['estoque.view']);
    Sanctum::actingAs($user, ['*']);

    $fromWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
    $toWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $transfer = StockTransfer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
    ]);

    $this->postJson("/api/v1/stock/transfers/{$transfer->id}/accept")->assertForbidden();
});

test('user WITH estoque.transfer.accept can reject stock transfer', function () {
    $user = stockUser($this->tenant, ['estoque.view', 'estoque.transfer.accept']);
    Sanctum::actingAs($user, ['*']);

    $fromWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
    $toWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $transfer = StockTransfer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'to_user_id' => $user->id,
    ]);

    $this->postJson("/api/v1/stock/transfers/{$transfer->id}/reject")->assertOk();
});

test('user WITHOUT estoque.transfer.accept gets 403 on reject stock transfer', function () {
    $user = stockUser($this->tenant, ['estoque.view']);
    Sanctum::actingAs($user, ['*']);

    $fromWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
    $toWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $transfer = StockTransfer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
    ]);

    $this->postJson("/api/v1/stock/transfers/{$transfer->id}/reject")->assertForbidden();
});

// ============================================================
// Warehouses
// ============================================================

test('user WITH estoque.warehouse.view can list warehouses', function () {
    $user = stockUser($this->tenant, ['estoque.warehouse.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/warehouses')->assertOk();
});

test('user WITHOUT estoque.warehouse.view gets 403 on list warehouses', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/warehouses')->assertForbidden();
});

test('user WITH estoque.warehouse.create can store warehouse', function () {
    $user = stockUser($this->tenant, ['estoque.warehouse.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/warehouses', [
        'name' => 'Armazem Central',
    ])->assertStatus(201);
});

test('user WITHOUT estoque.warehouse.create gets 403 on store warehouse', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/warehouses', [
        'name' => 'Armazem Central',
    ])->assertForbidden();
});

// ============================================================
// Stock Intelligence
// ============================================================

test('user WITH estoque.movement.view can access ABC curve', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/intelligence/abc-curve')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on ABC curve', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/intelligence/abc-curve')->assertForbidden();
});

test('user WITH estoque.movement.view can access turnover', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/intelligence/turnover')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on turnover', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/intelligence/turnover')->assertForbidden();
});

test('user WITH estoque.movement.view can access average cost', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/intelligence/average-cost')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on average cost', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/intelligence/average-cost')->assertForbidden();
});

// ============================================================
// Purchase Quotes
// ============================================================

test('user WITH estoque.movement.view can list purchase quotes', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/purchase-quotes')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on purchase quotes', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/purchase-quotes')->assertForbidden();
});

// ============================================================
// Material Requests
// ============================================================

test('user WITH estoque.movement.view can list material requests', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/material-requests')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on material requests', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/material-requests')->assertForbidden();
});

// ============================================================
// Used Stock Items
// ============================================================

test('user WITH estoque.movement.view can list used stock items', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/used-items')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on used stock items', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/used-items')->assertForbidden();
});

// ============================================================
// Serial Numbers
// ============================================================

test('user WITH estoque.movement.view can list serial numbers', function () {
    $user = stockUser($this->tenant, ['estoque.movement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/serial-numbers')->assertOk();
});

test('user WITHOUT estoque.movement.view gets 403 on serial numbers', function () {
    $user = stockUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/serial-numbers')->assertForbidden();
});
