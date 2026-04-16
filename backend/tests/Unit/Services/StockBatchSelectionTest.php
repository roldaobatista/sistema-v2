<?php

namespace Tests\Unit\Services;

use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WorkOrder;
use App\Services\StockService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StockBatchSelectionTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private StockService $stockService;

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

        $this->stockService = app(StockService::class);
    }

    // ── selectBatches ────────────────────────────────────────────────

    public function test_select_batches_fifo_returns_oldest_first(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create old batch (created 30 days ago)
        $oldBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-OLD',
            'created_at' => now()->subDays(30),
        ]);

        // Create new batch (created today)
        $newBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-NEW',
            'created_at' => now(),
        ]);

        // Stock for both batches
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $oldBatch->id,
            'quantity' => 10,
        ]);
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $newBatch->id,
            'quantity' => 5,
        ]);

        $batches = $this->stockService->selectBatches($product, $warehouse->id, 'FIFO');

        $this->assertCount(2, $batches);
        $this->assertEquals($oldBatch->id, $batches[0]['batch']->id, 'FIFO should return oldest batch first');
        $this->assertEquals($newBatch->id, $batches[1]['batch']->id);
        $this->assertEquals(10.0, $batches[0]['available']);
        $this->assertEquals(5.0, $batches[1]['available']);
    }

    public function test_select_batches_fefo_returns_earliest_expiry_first(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        // Batch expiring later
        $laterBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-LATER',
            'expires_at' => now()->addMonths(6),
            'created_at' => now()->subDays(10),
        ]);

        // Batch expiring sooner
        $soonerBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-SOONER',
            'expires_at' => now()->addMonth(),
            'created_at' => now()->subDays(5),
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $laterBatch->id,
            'quantity' => 8,
        ]);
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $soonerBatch->id,
            'quantity' => 12,
        ]);

        $batches = $this->stockService->selectBatches($product, $warehouse->id, 'FEFO');

        $this->assertCount(2, $batches);
        $this->assertEquals($soonerBatch->id, $batches[0]['batch']->id, 'FEFO should return earliest expiry first');
        $this->assertEquals($laterBatch->id, $batches[1]['batch']->id);
    }

    public function test_select_batches_fefo_batches_without_expiry_come_last(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        // Batch without expiry (created first)
        $noExpiryBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-NO-EXPIRY',
            'expires_at' => null,
            'created_at' => now()->subDays(60),
        ]);

        // Batch with expiry (created later)
        $expiryBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-WITH-EXPIRY',
            'expires_at' => now()->addMonths(3),
            'created_at' => now()->subDays(5),
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $noExpiryBatch->id,
            'quantity' => 10,
        ]);
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $expiryBatch->id,
            'quantity' => 5,
        ]);

        $batches = $this->stockService->selectBatches($product, $warehouse->id, 'FEFO');

        $this->assertCount(2, $batches);
        $this->assertEquals($expiryBatch->id, $batches[0]['batch']->id, 'FEFO: batch with expiry should come first');
        $this->assertEquals($noExpiryBatch->id, $batches[1]['batch']->id, 'FEFO: batch without expiry comes last');
    }

    public function test_select_batches_excludes_zero_quantity(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $emptyBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-EMPTY',
        ]);

        $fullBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-FULL',
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $emptyBatch->id,
            'quantity' => 0,
        ]);
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $fullBatch->id,
            'quantity' => 20,
        ]);

        $batches = $this->stockService->selectBatches($product, $warehouse->id);

        $this->assertCount(1, $batches);
        $this->assertEquals($fullBatch->id, $batches[0]['batch']->id);
    }

    public function test_select_batches_returns_empty_when_no_batches(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        // Only unbatched stock
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => null,
            'quantity' => 50,
        ]);

        $batches = $this->stockService->selectBatches($product, $warehouse->id);

        $this->assertCount(0, $batches);
    }

    // ── reserve() with batch selection ──────────────────────────────

    public function test_reserve_selects_oldest_batch_fifo(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
        ]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => null,
        ]);

        $oldBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-OLD',
            'created_at' => now()->subDays(30),
        ]);

        $newBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-NEW',
            'created_at' => now(),
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $oldBatch->id,
            'quantity' => 20,
        ]);
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $newBatch->id,
            'quantity' => 15,
        ]);

        $movement = $this->stockService->reserve($product, 5, $workOrder, $warehouse->id);

        $this->assertEquals($oldBatch->id, $movement->batch_id, 'FIFO reserve should use oldest batch');
        $this->assertEquals(5.0, (float) $movement->quantity);
        $this->assertEquals(StockMovementType::Reserve, $movement->type);
    }

    public function test_reserve_splits_across_batches_when_first_insufficient(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
        ]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => null,
        ]);

        $oldBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-OLD',
            'created_at' => now()->subDays(30),
        ]);

        $newBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-NEW',
            'created_at' => now(),
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $oldBatch->id,
            'quantity' => 3,
        ]);
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $newBatch->id,
            'quantity' => 10,
        ]);

        // Reserve 7 units — should consume 3 from old batch, 4 from new batch
        $firstMovement = $this->stockService->reserve($product, 7, $workOrder, $warehouse->id);

        $this->assertEquals($oldBatch->id, $firstMovement->batch_id, 'First movement should use oldest batch');
        $this->assertEquals(3.0, (float) $firstMovement->quantity, 'First movement should consume all from oldest batch');

        // Check second movement was created for the remaining 4 units
        $movements = StockMovement::where('product_id', $product->id)
            ->where('work_order_id', $workOrder->id)
            ->where('type', StockMovementType::Reserve->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $movements, 'Should create 2 movements when splitting across batches');
        $this->assertEquals($oldBatch->id, $movements[0]->batch_id);
        $this->assertEquals(3.0, (float) $movements[0]->quantity);
        $this->assertEquals($newBatch->id, $movements[1]->batch_id);
        $this->assertEquals(4.0, (float) $movements[1]->quantity);
    }

    public function test_reserve_falls_back_to_no_batch_when_product_has_no_batches(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
        ]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => null,
        ]);

        // Only unbatched stock
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => null,
            'quantity' => 50,
        ]);

        $movement = $this->stockService->reserve($product, 5, $workOrder, $warehouse->id);

        $this->assertNull($movement->batch_id, 'Should create movement without batch when no batches exist');
        $this->assertEquals(5.0, (float) $movement->quantity);
    }

    public function test_reserve_fefo_uses_earliest_expiry(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
        ]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => null,
        ]);

        // Batch expiring in 6 months (created first)
        $laterBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-LATER',
            'expires_at' => now()->addMonths(6),
            'created_at' => now()->subDays(30),
        ]);

        // Batch expiring in 1 month (created later)
        $soonerBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-SOONER',
            'expires_at' => now()->addMonth(),
            'created_at' => now()->subDays(5),
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $laterBatch->id,
            'quantity' => 20,
        ]);
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $soonerBatch->id,
            'quantity' => 15,
        ]);

        $movement = $this->stockService->reserve($product, 5, $workOrder, $warehouse->id, 'FEFO');

        $this->assertEquals($soonerBatch->id, $movement->batch_id, 'FEFO reserve should use earliest expiry batch');
    }

    public function test_deduct_selects_batch_fifo(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
        ]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => null,
        ]);

        $oldBatch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-OLD',
            'created_at' => now()->subDays(30),
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $oldBatch->id,
            'quantity' => 20,
        ]);

        $movement = $this->stockService->deduct($product, 5, $workOrder, $warehouse->id);

        $this->assertEquals($oldBatch->id, $movement->batch_id);
        $this->assertEquals(StockMovementType::Exit, $movement->type);
    }

    public function test_return_stock_selects_batch_fifo(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
        ]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => null,
        ]);

        $batch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-1',
            'created_at' => now()->subDays(10),
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity' => 20,
        ]);

        $movement = $this->stockService->returnStock($product, 3, $workOrder, $warehouse->id);

        $this->assertEquals($batch->id, $movement->batch_id);
        $this->assertEquals(StockMovementType::Return, $movement->type);
        $this->assertEquals(3.0, (float) $movement->quantity);
    }

    // ── Edge cases ──────────────────────────────────────────────────

    public function test_select_batches_only_considers_specified_warehouse(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse1 = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse2 = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $batch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-W2',
        ]);

        // Stock only in warehouse2
        WarehouseStock::create([
            'warehouse_id' => $warehouse2->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity' => 10,
        ]);

        $batches = $this->stockService->selectBatches($product, $warehouse1->id);
        $this->assertCount(0, $batches, 'Should not see batches from other warehouses');

        $batches = $this->stockService->selectBatches($product, $warehouse2->id);
        $this->assertCount(1, $batches);
    }

    public function test_manual_methods_still_accept_explicit_batch_id(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $batch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'BATCH-MANUAL',
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity' => 50,
        ]);

        // manualEntry already accepts batchId — should still work
        $movement = $this->stockService->manualEntry(
            product: $product,
            qty: 10,
            warehouseId: $warehouse->id,
            batchId: $batch->id,
            unitCost: 25.00,
            user: $this->user,
        );

        $this->assertEquals($batch->id, $movement->batch_id);
        $this->assertEquals(StockMovementType::Entry, $movement->type);
    }
}
