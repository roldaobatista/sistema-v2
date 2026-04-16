<?php

namespace Tests\Unit\Models;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StockDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── Product ──

    public function test_product_creation(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($p);
    }

    public function test_product_sku_unique(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'sku' => 'SKU-UNQ-001']);
        $this->expectException(QueryException::class);
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'sku' => 'SKU-UNQ-001']);
    }

    public function test_product_has_category(): void
    {
        $cat = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $p = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $cat->id,
        ]);
        $this->assertInstanceOf(ProductCategory::class, $p->category);
    }

    public function test_product_price_cast(): void
    {
        $p = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => '199.99',
        ]);
        $this->assertEquals('199.99', $p->price);
    }

    public function test_product_cost_cast(): void
    {
        $p = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cost' => '89.50',
        ]);
        $this->assertEquals('89.50', $p->cost);
    }

    public function test_product_soft_deletes(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $p->delete();
        $this->assertNotNull(Product::withTrashed()->find($p->id));
    }

    public function test_product_type_product(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'product']);
        $this->assertEquals('product', $p->type);
    }

    public function test_product_type_service(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'service']);
        $this->assertEquals('service', $p->type);
    }

    // ── ProductCategory ──

    public function test_category_has_many_products(): void
    {
        $cat = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Product::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $cat->id,
        ]);
        $this->assertGreaterThanOrEqual(3, $cat->products()->count());
    }

    public function test_category_creation(): void
    {
        $cat = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($cat);
        $this->assertTrue($cat->is_active);
    }

    // ── Warehouse ──

    public function test_warehouse_creation(): void
    {
        $w = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($w);
    }

    public function test_warehouse_has_stock(): void
    {
        $w = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        WarehouseStock::factory()->create([
            'warehouse_id' => $w->id,
            'product_id' => $p->id,
            'quantity' => 100,
        ]);
        $this->assertGreaterThanOrEqual(1, $w->stocks()->count());
    }

    public function test_warehouse_is_main(): void
    {
        $w = Warehouse::factory()->create(['tenant_id' => $this->tenant->id, 'is_main' => true]);
        $this->assertTrue($w->is_main);
    }

    // ── StockMovement ──

    public function test_stock_movement_entry(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $w = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $sm = StockMovement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $p->id,
            'warehouse_id' => $w->id,
            'type' => 'entry',
            'quantity' => 50,
        ]);
        $this->assertEquals(StockMovementType::Entry, $sm->type);
        $this->assertEquals(50, (float) $sm->quantity);
    }

    public function test_stock_movement_exit(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $w = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $sm = StockMovement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $p->id,
            'warehouse_id' => $w->id,
            'type' => 'exit',
            'quantity' => 10,
        ]);
        $this->assertEquals(StockMovementType::Exit, $sm->type);
    }

    public function test_stock_movement_belongs_to_product(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $w = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $sm = StockMovement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $p->id,
            'warehouse_id' => $w->id,
        ]);
        $this->assertInstanceOf(Product::class, $sm->product);
    }

    // ── StockTransfer ──

    public function test_stock_transfer_between_warehouses(): void
    {
        $w1 = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $w2 = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $st = StockTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $w1->id,
            'to_warehouse_id' => $w2->id,
            'created_by' => $this->user->id,
        ]);
        $this->assertEquals($w1->id, $st->from_warehouse_id);
        $this->assertEquals($w2->id, $st->to_warehouse_id);
    }

    // ── WarehouseStock ──

    public function test_warehouse_stock_quantity(): void
    {
        $w = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $ws = WarehouseStock::factory()->create([
            'warehouse_id' => $w->id,
            'product_id' => $p->id,
            'quantity' => 200,
        ]);
        $this->assertEquals(200, $ws->quantity);
    }

    public function test_warehouse_stock_min_quantity_alert(): void
    {
        $w = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id, 'stock_min' => 50]);
        $ws = WarehouseStock::factory()->create([
            'warehouse_id' => $w->id,
            'product_id' => $p->id,
            'quantity' => 10,
        ]);
        $this->assertTrue($ws->quantity < $p->stock_min);
    }

    // ── Scopes ──

    public function test_product_scope_by_type(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'product']);
        $results = Product::where('type', 'product')->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_product_scope_search(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Peso Padrão 1kg']);
        $results = Product::where('name', 'like', '%Peso Padrão%')->get();
        $this->assertTrue($results->contains('id', $p->id));
    }

    public function test_product_scope_low_stock(): void
    {
        $w = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id, 'stock_min' => 100]);
        WarehouseStock::factory()->create([
            'warehouse_id' => $w->id,
            'product_id' => $p->id,
            'quantity' => 5,
        ]);
        $lowStock = WarehouseStock::where('quantity', '<', 100)->get();
        $this->assertGreaterThanOrEqual(1, $lowStock->count());
    }
}
