<?php

namespace Tests\Unit\Models;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StockProductModelsTest extends TestCase
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

    // ── Product — Relationships ──

    public function test_product_belongs_to_tenant(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $product->tenant_id);
    }

    public function test_product_fillable_fields(): void
    {
        $p = new Product;
        $fillable = $p->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('code', $fillable);
    }

    public function test_product_soft_delete(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $product->delete();

        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }

    // ── Warehouse — Relationships ──

    public function test_warehouse_belongs_to_tenant(): void
    {
        $wh = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $wh->tenant_id);
    }

    public function test_warehouse_has_many_stocks(): void
    {
        $wh = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertInstanceOf(HasMany::class, $wh->stocks());
    }

    public function test_warehouse_fillable_contains_name(): void
    {
        $wh = new Warehouse;
        $this->assertContains('name', $wh->getFillable());
    }

    // ── StockMovement — Relationships ──

    public function test_stock_movement_belongs_to_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $wh = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'type' => 'entry',
            'quantity' => 10,
            'unit_cost' => '50.00',
            'notes' => 'Test entry',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Product::class, $movement->product);
    }

    public function test_stock_movement_belongs_to_warehouse(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $wh = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'type' => 'entry',
            'quantity' => 5,
            'unit_cost' => '25.00',
            'notes' => 'Test warehouse',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Warehouse::class, $movement->warehouse);
    }

    public function test_stock_movement_casts_decimal(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $wh = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'type' => 'entry',
            'quantity' => 15,
            'unit_cost' => '123.45',
            'notes' => 'Decimal test',
            'created_by' => $this->user->id,
        ]);

        $movement->refresh();
        $this->assertEquals('123.45', $movement->unit_cost);
    }

    // ── StockTransfer — Relationships ──

    public function test_stock_transfer_belongs_to_warehouses(): void
    {
        $from = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $to = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $transfer = StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Warehouse::class, $transfer->fromWarehouse);
        $this->assertInstanceOf(Warehouse::class, $transfer->toWarehouse);
    }

    public function test_stock_transfer_has_many_items(): void
    {
        $from = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $to = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $transfer = StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $transfer->items());
    }

    // ── Inventory — Relationships ──

    public function test_inventory_belongs_to_warehouse(): void
    {
        $wh = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $inv = Inventory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $wh->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Warehouse::class, $inv->warehouse);
    }

    public function test_inventory_has_many_items(): void
    {
        $wh = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $inv = Inventory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $wh->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $inv->items());
    }
}
