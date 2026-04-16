<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
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

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'stock_qty' => 10,
    ]);

    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Warehouse',
        'code' => 'WH-001',
        'type' => Warehouse::TYPE_FIXED,
        'is_active' => true,
    ]);

    WarehouseStock::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 10,
    ]);
});

test('negative quantity in stock movement is rejected', function () {
    $response = $this->postJson('/api/v1/stock/movements', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'exit',
        'quantity' => -5,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['quantity']);
});

test('zero quantity in stock movement is rejected', function () {
    $response = $this->postJson('/api/v1/stock/movements', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'exit',
        'quantity' => 0,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['quantity']);
});

test('stock entry with valid quantity is accepted', function () {
    $response = $this->postJson('/api/v1/stock/movements', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'entry',
        'quantity' => 5,
    ]);

    $response->assertSuccessful();
});

test('stock movement requires product_id', function () {
    $response = $this->postJson('/api/v1/stock/movements', [
        'warehouse_id' => $this->warehouse->id,
        'type' => 'exit',
        'quantity' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['product_id']);
});

test('stock movement requires warehouse_id', function () {
    $response = $this->postJson('/api/v1/stock/movements', [
        'product_id' => $this->product->id,
        'type' => 'exit',
        'quantity' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['warehouse_id']);
});

test('stock movement requires valid type', function () {
    $response = $this->postJson('/api/v1/stock/movements', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'invalid_type',
        'quantity' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

test('stock movement type must be one of entry exit reserve return adjustment', function () {
    foreach (['entry', 'exit', 'reserve', 'return', 'adjustment'] as $type) {
        $response = $this->postJson('/api/v1/stock/movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => $type,
            'quantity' => 1,
        ]);

        expect($response->status())->not->toBe(422, "Type '{$type}' should be valid");
    }
});

test('stock transfer requires different source and destination warehouses', function () {
    $response = $this->postJson('/api/v1/stock/transfers', [
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $this->warehouse->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['to_warehouse_id']);
});

test('stock transfer requires at least one item', function () {
    $otherWarehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Other Warehouse',
        'code' => 'WH-002',
        'type' => Warehouse::TYPE_FIXED,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/stock/transfers', [
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $otherWarehouse->id,
        'items' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

test('stock movement notes max length is enforced', function () {
    $response = $this->postJson('/api/v1/stock/movements', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'entry',
        'quantity' => 1,
        'notes' => str_repeat('A', 501),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['notes']);
});
