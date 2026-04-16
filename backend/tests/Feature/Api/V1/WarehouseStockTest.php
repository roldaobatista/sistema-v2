<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseStockTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Warehouse $warehouse;

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

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'MAIN-001',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product',
            'code' => 'TP-001',
            'unit' => 'un',
            'stock_qty' => 50,
        ]);
    }

    public function test_index_returns_paginated_stocks(): void
    {
        WarehouseStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
        ]);

        $response = $this->getJson('/api/v1/warehouse-stocks');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_index_filters_by_warehouse_id(): void
    {
        $otherWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Other WH',
            'code' => 'OTH-001',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
        ]);
        WarehouseStock::create([
            'warehouse_id' => $otherWarehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
        ]);

        $response = $this->getJson("/api/v1/warehouse-stocks?warehouse_id={$this->warehouse->id}");

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals($this->warehouse->id, $item['warehouse_id']);
        }
    }

    public function test_index_filters_by_product_id(): void
    {
        $product2 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Another Product',
            'code' => 'AP-001',
            'unit' => 'un',
            'stock_qty' => 20,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
        ]);
        WarehouseStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product2->id,
            'quantity' => 25,
        ]);

        $response = $this->getJson("/api/v1/warehouse-stocks?product_id={$this->product->id}");

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals($this->product->id, $item['product_id']);
        }
    }

    public function test_index_hides_empty_stocks_by_default(): void
    {
        WarehouseStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 0,
        ]);

        $response = $this->getJson('/api/v1/warehouse-stocks');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertGreaterThan(0, (float) $item['quantity']);
        }
    }

    public function test_index_shows_empty_stocks_when_hide_empty_is_false(): void
    {
        WarehouseStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 0,
        ]);

        $response = $this->getJson('/api/v1/warehouse-stocks?hide_empty=false');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        $hasZero = collect($items)->contains(fn ($i) => (float) $i['quantity'] === 0.0);
        $this->assertTrue($hasZero);
    }

    public function test_by_warehouse_returns_stocks_for_warehouse(): void
    {
        WarehouseStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 75,
        ]);

        $response = $this->getJson("/api/v1/warehouses/{$this->warehouse->id}/stocks");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_by_warehouse_rejects_other_tenant_warehouse(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherWh = Warehouse::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant WH',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/warehouses/{$otherWh->id}/stocks");

        $response->assertStatus(404);
    }

    public function test_by_product_returns_stocks_for_product(): void
    {
        WarehouseStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 60,
        ]);

        $response = $this->getJson("/api/v1/products/{$this->product->id}/warehouse-stocks");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_by_product_rejects_other_tenant_product(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherProduct = Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Product',
            'code' => 'OTP-001',
            'unit' => 'un',
            'stock_qty' => 0,
        ]);

        $response = $this->getJson("/api/v1/products/{$otherProduct->id}/warehouse-stocks");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/warehouse-stocks');

        $response->assertUnauthorized();
    }
}
