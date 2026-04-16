<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Warehouse $warehouseFrom;

    private Warehouse $warehouseTo;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->warehouseFrom = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Armazém Origem',
            'code' => 'ORIG-001',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $this->warehouseTo = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Armazém Destino',
            'code' => 'DEST-001',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Transfer',
            'code' => 'TRF-001',
            'unit' => 'un',
            'stock_qty' => 100,
        ]);

        // Give from warehouse enough stock
        WarehouseStock::create([
            'warehouse_id' => $this->warehouseFrom->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
        ]);
    }

    public function test_index_returns_paginated_transfers(): void
    {
        StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseTo->id,
            'status' => StockTransfer::STATUS_COMPLETED,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/stock/transfers');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_index_filters_by_status(): void
    {
        StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseTo->id,
            'status' => StockTransfer::STATUS_COMPLETED,
            'created_by' => $this->user->id,
        ]);
        StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseTo->id,
            'status' => StockTransfer::STATUS_PENDING_ACCEPTANCE,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/stock/transfers?status=completed');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals('completed', $item['status']);
        }
    }

    public function test_index_filters_by_from_warehouse(): void
    {
        StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseTo->id,
            'status' => StockTransfer::STATUS_COMPLETED,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/stock/transfers?from_warehouse_id={$this->warehouseFrom->id}");

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals($this->warehouseFrom->id, $item['from_warehouse_id']);
        }
    }

    public function test_show_returns_single_transfer(): void
    {
        $transfer = StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseTo->id,
            'status' => StockTransfer::STATUS_COMPLETED,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/stock/transfers/{$transfer->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($transfer->id, $data['id']);
    }

    public function test_show_rejects_transfer_from_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherWh1 = Warehouse::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other WH1',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);
        $otherWh2 = Warehouse::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other WH2',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);
        $otherTransfer = StockTransfer::create([
            'tenant_id' => $otherTenant->id,
            'from_warehouse_id' => $otherWh1->id,
            'to_warehouse_id' => $otherWh2->id,
            'status' => StockTransfer::STATUS_COMPLETED,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/stock/transfers/{$otherTransfer->id}");

        $response->assertStatus(404);
    }

    public function test_store_creates_transfer_between_fixed_warehouses(): void
    {
        $response = $this->postJson('/api/v1/stock/transfers', [
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseTo->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5],
            ],
            'notes' => 'Test transfer',
        ]);

        $response->assertStatus(201);
    }

    public function test_store_fails_with_same_origin_and_destination(): void
    {
        $response = $this->postJson('/api/v1/stock/transfers', [
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseFrom->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('to_warehouse_id');
    }

    public function test_store_fails_without_items(): void
    {
        $response = $this->postJson('/api/v1/stock/transfers', [
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseTo->id,
            'items' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items');
    }

    public function test_store_fails_with_zero_quantity(): void
    {
        $response = $this->postJson('/api/v1/stock/transfers', [
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseTo->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 0],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_warehouse_from_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherWh = Warehouse::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant WH',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/stock/transfers', [
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $otherWh->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('to_warehouse_id');
    }

    public function test_my_pending_filter_works(): void
    {
        $transfer = StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseTo->id,
            'status' => StockTransfer::STATUS_PENDING_ACCEPTANCE,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/stock/transfers?my_pending=true');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals(StockTransfer::STATUS_PENDING_ACCEPTANCE, $item['status']);
            $this->assertEquals($this->user->id, $item['to_user_id']);
        }
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/stock/transfers');

        $response->assertUnauthorized();
    }

    public function test_unauthorized_user_cannot_create_transfer_403(): void
    {
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->postJson('/api/v1/stock/transfers', [
            'from_warehouse_id' => $this->warehouseFrom->id,
            'to_warehouse_id' => $this->warehouseTo->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5],
            ],
            'notes' => 'Test transfer 403',
        ]);

        $response->assertStatus(403);
    }
}
