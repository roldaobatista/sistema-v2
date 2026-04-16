<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StockServiceTest extends TestCase
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

    public function test_stock_entry_creates_movement(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'quantity' => 10,
            'unit_cost' => '50.00',
            'notes' => 'Compra',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals('entry', $movement->type->value ?? $movement->type);
        $this->assertEquals(10, $movement->quantity);
    }

    public function test_stock_exit_creates_movement(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'exit',
            'quantity' => -5,
            'unit_cost' => '50.00',
            'notes' => 'Consumo em OS',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals('exit', $movement->type->value ?? $movement->type);
    }

    public function test_stock_transfer_between_warehouses(): void
    {
        $from = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $to = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $transfer = StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'status' => 'pending_acceptance',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals('pending_acceptance', $transfer->status);
        $this->assertEquals($from->id, $transfer->from_warehouse_id);
        $this->assertEquals($to->id, $transfer->to_warehouse_id);
    }

    public function test_warehouse_stock_tracks_quantity(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $stock = WarehouseStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
        ]);

        $this->assertEquals(100, $stock->quantity);
    }

    public function test_stock_movement_belongs_to_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'quantity' => 5,
            'unit_cost' => '25.00',
            'notes' => 'Test',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Product::class, $movement->product);
    }

    public function test_multiple_movements_for_same_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'quantity' => 10,
            'unit_cost' => '50.00',
            'notes' => 'Entry 1',
            'created_by' => $this->user->id,
        ]);

        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'quantity' => 20,
            'unit_cost' => '45.00',
            'notes' => 'Entry 2',
            'created_by' => $this->user->id,
        ]);

        $count = StockMovement::where('product_id', $product->id)->count();
        $this->assertEquals(2, $count);
    }

    public function test_product_can_have_code(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BAL-001',
        ]);

        $this->assertEquals('BAL-001', $product->code);
    }

    public function test_product_decimal_casts(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cost_price' => '150.75',
            'sell_price' => '250.99',
        ]);

        $product->refresh();
        $this->assertEquals('150.75', $product->cost_price);
        $this->assertEquals('250.99', $product->sell_price);
    }
}
