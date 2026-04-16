<?php

namespace Tests\Feature;

use App\Events\WorkOrderInvoiced;
use App\Listeners\HandleWorkOrderInvoicing;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\CommissionService;
use App\Services\InvoicingService;
use App\Services\StockService;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class WorkOrderStockTest extends TestCase
{
    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // Ensure tenant context is set for BelongsToTenant scope
        app()->instance('current_tenant_id', $this->tenant->id);

        // Create default warehouse for stock operations
        Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'fixed',
            'name' => 'Central Warehouse',
            'code' => 'CENTRAL',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    public function test_invoicing_does_not_double_deduct_stock_when_product_is_already_reserved(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
            'stock_qty' => 20,
            'sell_price' => 50,
        ]);
        $warehouseId = Warehouse::where('code', 'CENTRAL')->value('id');

        WarehouseStock::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_INVOICED,
            'total' => 150,
        ]);

        $workOrder->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => WorkOrderItem::TYPE_PRODUCT,
            'reference_id' => $product->id,
            'description' => $product->name,
            'quantity' => 3,
            'unit_price' => 50,
            'warehouse_id' => $warehouseId,
        ]);

        $this->assertEquals(17.0, (float) $product->fresh()->stock_qty);

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'work_order_id' => $workOrder->id,
            'invoice_id' => $invoice->id,
            'created_by' => $this->user->id,
            'amount' => 150,
            'amount_paid' => 0,
        ]);

        $invoicingService = $this->mock(InvoicingService::class);
        $commissionService = $this->mock(CommissionService::class);

        $invoicingService
            ->shouldReceive('generateFromWorkOrder')
            ->once()
            ->andReturn([
                'invoice' => $invoice,
                'ar' => $receivable,
                'receivables' => [$receivable],
            ]);

        $commissionService->shouldReceive('calculateAndGenerate')->once();

        $listener = new HandleWorkOrderInvoicing(
            $invoicingService,
            app(StockService::class),
            $commissionService,
        );

        $listener->handle(new WorkOrderInvoiced($workOrder, $this->user, WorkOrder::STATUS_DELIVERED));

        $this->assertEquals(17.0, (float) $product->fresh()->stock_qty);
        $this->assertSame(0.0, (float) StockMovement::where('work_order_id', $workOrder->id)
            ->where('product_id', $product->id)
            ->where('type', 'exit')
            ->sum('quantity'));
    }

    public function test_invoicing_deducts_stock_once_when_item_was_created_without_reservation(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
            'stock_qty' => 20,
            'sell_price' => 50,
        ]);
        $warehouseId = Warehouse::where('code', 'CENTRAL')->value('id');

        WarehouseStock::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_INVOICED,
            'total' => 150,
        ]);

        WorkOrderItem::withoutEvents(function () use ($workOrder, $product, $warehouseId): void {
            $workOrder->items()->create([
                'tenant_id' => $this->tenant->id,
                'type' => WorkOrderItem::TYPE_PRODUCT,
                'reference_id' => $product->id,
                'description' => $product->name,
                'quantity' => 3,
                'unit_price' => 50,
                'warehouse_id' => $warehouseId,
                'total' => 150,
            ]);
        });

        $this->assertEquals(20.0, (float) $product->fresh()->stock_qty);

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'work_order_id' => $workOrder->id,
            'invoice_id' => $invoice->id,
            'created_by' => $this->user->id,
            'amount' => 150,
            'amount_paid' => 0,
        ]);

        $invoicingService = $this->mock(InvoicingService::class);
        $commissionService = $this->mock(CommissionService::class);

        $invoicingService
            ->shouldReceive('generateFromWorkOrder')
            ->twice()
            ->andReturn([
                'invoice' => $invoice,
                'ar' => $receivable,
                'receivables' => [$receivable],
            ]);

        $commissionService->shouldReceive('calculateAndGenerate')->twice();

        $listener = new HandleWorkOrderInvoicing(
            $invoicingService,
            app(StockService::class),
            $commissionService,
        );

        $event = new WorkOrderInvoiced($workOrder, $this->user, WorkOrder::STATUS_DELIVERED);
        $listener->handle($event);
        $listener->handle($event);

        $this->assertEquals(17.0, (float) $product->fresh()->stock_qty);
        $this->assertSame(3.0, (float) StockMovement::where('work_order_id', $workOrder->id)
            ->where('product_id', $product->id)
            ->where('type', 'exit')
            ->sum('quantity'));
    }

    public function test_changing_product_references_correctly_updates_stock()
    {
        // 1. Setup Products
        $productA = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
            'stock_qty' => 100,
            'sell_price' => 50,
        ]);

        WarehouseStock::create([
            'warehouse_id' => Warehouse::where('code', 'CENTRAL')->first()->id,
            'product_id' => $productA->id,
            'quantity' => 100,
        ]);

        $productB = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
            'stock_qty' => 100,
            'sell_price' => 80,
        ]);

        WarehouseStock::create([
            'warehouse_id' => Warehouse::where('code', 'CENTRAL')->first()->id,
            'product_id' => $productB->id,
            'quantity' => 100,
        ]);

        // 2. Create OS with Product A (Qty 10)
        $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);

        $item = $workOrder->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'product',
            'reference_id' => $productA->id,
            'description' => $productA->name,
            'quantity' => 10,
            'unit_price' => $productA->sell_price,
        ]);

        // Assert Product A stock reduced
        $this->assertEquals(90, $productA->fresh()->stock_qty);
        $this->assertEquals(100, $productB->fresh()->stock_qty);

        // 3. Change Item to Product B (Qty 5)
        $item->update([
            'reference_id' => $productB->id,
            'quantity' => 5,
        ]);

        // 4. Assert Stock Correction
        // Product A should be fully refunded (100)
        $this->assertEquals(100, $productA->fresh()->stock_qty, 'Product A stock should be restored');

        // Product B should be reduced (95)
        $this->assertEquals(95, $productB->fresh()->stock_qty, 'Product B stock should be reduced');
    }

    public function test_deleting_item_restores_stock()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
            'stock_qty' => 50,
        ]);

        WarehouseStock::create([
            'warehouse_id' => Warehouse::where('code', 'CENTRAL')->first()->id,
            'product_id' => $product->id,
            'quantity' => 50,
        ]);

        $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);

        $item = $workOrder->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'product',
            'reference_id' => $product->id,
            'description' => $product->name,
            'quantity' => 5, // Reserve 5
            'unit_price' => 10,
        ]);

        $this->assertEquals(45, $product->fresh()->stock_qty);

        $item->delete();

        $this->assertEquals(50, $product->fresh()->stock_qty);
    }
}
