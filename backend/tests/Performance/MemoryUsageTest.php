<?php

/**
 * Memory Usage Tests
 *
 * Ensures that high-volume operations do not consume excessive memory.
 * Thresholds are set generously (64-128 MB) so tests pass on CI while
 * still catching unbounded memory growth (e.g., loading 10k models into
 * a single collection without chunking).
 */

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WorkOrder;

// ─── Large customer list does not blow memory ───────────────────────

test('listing 2000 customers does not exceed 64MB additional memory', function () {
    Customer::factory()->count(2000)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $memoryBefore = memory_get_usage(true);

    $response = $this->getJson('/api/v1/customers?per_page=50');

    $memoryPeak = memory_get_peak_usage(true);
    $memoryUsedMb = ($memoryPeak - $memoryBefore) / 1024 / 1024;

    $response->assertOk();
    expect($memoryUsedMb)->toBeLessThan(64, "Customer list used {$memoryUsedMb} MB for 2000 records");
});

// ─── Large work order list memory ───────────────────────────────────

test('listing 2000 work orders does not exceed 64MB additional memory', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->count(2000)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $memoryBefore = memory_get_usage(true);

    $response = $this->getJson('/api/v1/work-orders?per_page=50');

    $memoryPeak = memory_get_peak_usage(true);
    $memoryUsedMb = ($memoryPeak - $memoryBefore) / 1024 / 1024;

    $response->assertOk();
    expect($memoryUsedMb)->toBeLessThan(64, "Work-order list used {$memoryUsedMb} MB for 2000 records");
});

// ─── Financial CSV export memory ────────────────────────────────────

test('financial CSV export of 3000 receivables does not exceed 128MB', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    AccountReceivable::factory()->count(3000)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'due_date' => now(),
    ]);

    $from = now()->subDay()->format('Y-m-d');
    $to = now()->addDay()->format('Y-m-d');

    $memoryBefore = memory_get_usage(true);

    $response = $this->getJson("/api/v1/financial/export/csv?type=receivable&from={$from}&to={$to}");

    $memoryPeak = memory_get_peak_usage(true);
    $memoryUsedMb = ($memoryPeak - $memoryBefore) / 1024 / 1024;

    $response->assertOk();
    expect($memoryUsedMb)->toBeLessThan(128, "Financial CSV export used {$memoryUsedMb} MB for 3000 records");
});

// ─── Dashboard with heavy dataset memory ────────────────────────────

test('dashboard stats with 2000 records does not exceed 64MB additional memory', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->count(1000)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    AccountReceivable::factory()->count(1000)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $memoryBefore = memory_get_usage(true);

    $response = $this->getJson('/api/v1/dashboard-stats');

    $memoryPeak = memory_get_peak_usage(true);
    $memoryUsedMb = ($memoryPeak - $memoryBefore) / 1024 / 1024;

    $response->assertOk();
    // Dashboard uses aggregates so memory should stay low
    expect($memoryUsedMb)->toBeLessThan(64, "Dashboard used {$memoryUsedMb} MB with 2000 records");
});

// ─── Bulk stock movement insert memory ──────────────────────────────

test('bulk stock movement creation does not exceed 64MB additional memory', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'stock_qty' => 0,
    ]);

    $warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Memory WH',
        'code' => 'WH-MEM',
        'type' => Warehouse::TYPE_FIXED,
        'is_active' => true,
    ]);

    // Bulk-insert 5000 movements directly (simulating heavy import)
    $rows = [];
    $now = now();
    for ($i = 0; $i < 5000; $i++) {
        $rows[] = [
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'quantity' => 1,
            'unit_cost' => 10,
            'reference' => 'bulk-test',
            'created_by' => $this->user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    $memoryBefore = memory_get_usage(true);

    foreach (array_chunk($rows, 500) as $chunk) {
        StockMovement::insert($chunk);
    }

    // Now list them paginated
    $this->getJson('/api/v1/stock/movements?per_page=50')->assertOk();

    $memoryPeak = memory_get_peak_usage(true);
    $memoryUsedMb = ($memoryPeak - $memoryBefore) / 1024 / 1024;

    expect($memoryUsedMb)->toBeLessThan(64, "Bulk insert + listing used {$memoryUsedMb} MB for 5000 movements");
});
