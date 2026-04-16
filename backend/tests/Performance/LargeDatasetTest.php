<?php

/**
 * Large Dataset Performance Tests
 *
 * Ensures that list endpoints remain performant and correctly paginate
 * when the database contains a high volume of records.
 *
 * Timing thresholds are intentionally generous (2-5 s) to prevent CI
 * flakiness while still catching catastrophic regressions.
 */

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WorkOrder;

// ─── 1000 Customers ─────────────────────────────────────────────────

test('customer list performs well with 1000 records', function () {
    Customer::factory()->count(1000)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $start = microtime(true);

    $response = $this->getJson('/api/v1/customers?per_page=50');

    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(3.0, "Customer list took {$duration}s with 1000 records");

    // Verify pagination -- should not return all 1000 at once
    $json = $response->json();
    if (isset($json['data'])) {
        expect(count($json['data']))->toBeLessThanOrEqual(50);
    }
    if (isset($json['meta']['total'])) {
        expect($json['meta']['total'])->toBeGreaterThanOrEqual(1000);
    }
});

// ─── 1000 Work Orders ───────────────────────────────────────────────

test('work order list performs well with 1000 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->count(1000)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $start = microtime(true);

    $response = $this->getJson('/api/v1/work-orders?per_page=50');

    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(3.0, "Work-order list took {$duration}s with 1000 records");

    $json = $response->json();
    if (isset($json['data'])) {
        expect(count($json['data']))->toBeLessThanOrEqual(50);
    }
});

// ─── 2000 Stock Movements ───────────────────────────────────────────

test('stock movement list performs well with 2000 records', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Perf Warehouse',
        'code' => 'WH-LG',
        'type' => Warehouse::TYPE_FIXED,
        'is_active' => true,
    ]);

    // Bulk-insert for speed
    $rows = [];
    $now = now();
    for ($i = 0; $i < 2000; $i++) {
        $rows[] = [
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'quantity' => 1,
            'unit_cost' => 10,
            'reference' => 'perf-test',
            'created_by' => $this->user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    // Insert in chunks to avoid query size limits
    foreach (array_chunk($rows, 500) as $chunk) {
        StockMovement::insert($chunk);
    }

    $start = microtime(true);

    $response = $this->getJson('/api/v1/stock/movements?per_page=50');

    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(3.0, "Stock movements took {$duration}s with 2000 records");
});

// ─── 1000 Accounts Receivable ───────────────────────────────────────

test('accounts receivable list performs well with 1000 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    AccountReceivable::factory()->count(1000)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $start = microtime(true);

    $response = $this->getJson('/api/v1/accounts-receivable?per_page=50');

    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(3.0, "Accounts-receivable list took {$duration}s with 1000 records");
});

// ─── 1000 Accounts Payable ──────────────────────────────────────────

test('accounts payable list performs well with 1000 records', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

    AccountPayable::factory()->count(1000)->create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
    ]);

    $start = microtime(true);

    $response = $this->getJson('/api/v1/accounts-payable?per_page=50');

    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(3.0, "Accounts-payable list took {$duration}s with 1000 records");
});

// ─── 1000 Quotes ────────────────────────────────────────────────────

test('quote list performs well with 1000 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    Quote::factory()->count(1000)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);

    $start = microtime(true);

    $response = $this->getJson('/api/v1/quotes?per_page=50');

    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(3.0, "Quote list took {$duration}s with 1000 records");
});

// ─── 1000 Equipments ────────────────────────────────────────────────

test('equipment list performs well with 1000 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    Equipment::factory()->count(1000)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    $start = microtime(true);

    $response = $this->getJson('/api/v1/equipments?per_page=25');

    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(3.0, "Equipment list took {$duration}s with 1000 records");
});

// ─── 1000 Service Calls ─────────────────────────────────────────────

test('service call list performs well with 1000 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    ServiceCall::factory()->count(1000)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $start = microtime(true);

    $response = $this->getJson('/api/v1/service-calls?per_page=50');

    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(3.0, "Service-call list took {$duration}s with 1000 records");
});

// ─── Dashboard with large data ──────────────────────────────────────

test('dashboard stats perform well with large dataset', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->count(500)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    AccountReceivable::factory()->count(500)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $start = microtime(true);

    $response = $this->getJson('/api/v1/dashboard-stats');

    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(5.0, "Dashboard stats took {$duration}s with large dataset");
});

// ─── Financial CSV export ───────────────────────────────────────────

test('financial CSV export performs well with 2000 receivables', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    AccountReceivable::factory()->count(2000)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'due_date' => now(),
    ]);

    $from = now()->subDay()->format('Y-m-d');
    $to = now()->addDay()->format('Y-m-d');

    $start = microtime(true);

    $response = $this->getJson("/api/v1/financial/export/csv?type=receivable&from={$from}&to={$to}");

    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(5.0, "Financial CSV export took {$duration}s with 2000 receivables");
});
