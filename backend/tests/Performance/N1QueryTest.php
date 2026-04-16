<?php

/**
 * N+1 Query Detection Tests
 *
 * Validates that list endpoints use proper eager loading and do not
 * degrade linearly as the number of records grows.
 *
 * Methodology: create N records with relationships, hit the list endpoint,
 * and assert the total query count stays below a fixed ceiling.
 * The ceiling is generous (15) to avoid flakiness while still catching
 * true N+1 regressions (which would generate 20+ queries for 20 records).
 */

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

// ─── Helpers ─────────────────────────────────────────────────────────

function resetQueryLog(): void
{
    DB::flushQueryLog();
    DB::enableQueryLog();
}

function queryCount(): int
{
    return count(DB::getQueryLog());
}

// ─── Customer list (with contacts, assignedSeller) ──────────────────

test('customer list does not have N+1 queries', function () {
    $customers = Customer::factory()
        ->count(20)
        ->create(['tenant_id' => $this->tenant->id]);

    // Add contacts per customer to exercise the eager load
    $customers->each(function (Customer $c) {
        for ($i = 0; $i < 2; $i++) {
            CustomerContact::create([
                'tenant_id' => $c->tenant_id,
                'customer_id' => $c->id,
                'name' => fake()->name(),
                'role' => 'contact',
                'phone' => fake()->phoneNumber(),
                'email' => fake()->email(),
                'is_primary' => $i === 0,
            ]);
        }
    });

    resetQueryLog();

    $response = $this->getJson('/api/v1/customers');

    $response->assertOk();
    expect(queryCount())->toBeLessThan(15, 'Potential N+1 on customer list');
});

// ─── Work-order list (customer, assignee, equipment, seller, …) ─────

test('work order list does not have N+1 queries', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    resetQueryLog();

    $response = $this->getJson('/api/v1/work-orders');

    $response->assertOk();
    expect(queryCount())->toBeLessThan(15, 'Potential N+1 on work-order list');
});

// ─── Accounts Receivable list (customer, workOrder, chartOfAccount) ──

test('accounts receivable list does not have N+1 queries', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    AccountReceivable::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    resetQueryLog();

    $response = $this->getJson('/api/v1/accounts-receivable');

    $response->assertOk();
    expect(queryCount())->toBeLessThan(15, 'Potential N+1 on accounts-receivable list');
});

// ─── Accounts Payable list (supplier, category, chartOfAccount, creator)

test('accounts payable list does not have N+1 queries', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

    AccountPayable::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
    ]);

    resetQueryLog();

    $response = $this->getJson('/api/v1/accounts-payable');

    $response->assertOk();
    expect(queryCount())->toBeLessThan(15, 'Potential N+1 on accounts-payable list');
});

// ─── Quotes list (customer, seller, tags) ───────────────────────────

test('quote list does not have N+1 queries', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    Quote::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);

    resetQueryLog();

    $response = $this->getJson('/api/v1/quotes');

    $response->assertOk();
    expect(queryCount())->toBeLessThan(15, 'Potential N+1 on quote list');
});

// ─── Equipment list (customer, responsible) ─────────────────────────

test('equipment list does not have N+1 queries', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    Equipment::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    resetQueryLog();

    $response = $this->getJson('/api/v1/equipments');

    $response->assertOk();
    expect(queryCount())->toBeLessThan(15, 'Potential N+1 on equipment list');
});

// ─── Stock Movements list (product, createdByUser, workOrder) ───────

test('stock movements list does not have N+1 queries', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Warehouse',
        'code' => 'WH-PERF',
        'type' => Warehouse::TYPE_FIXED,
        'is_active' => true,
    ]);

    // Seed enough stock
    WarehouseStock::create([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity' => 9999,
    ]);
    $product->update(['stock_qty' => 9999]);

    // Create movements directly to avoid service guards
    for ($i = 0; $i < 20; $i++) {
        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'quantity' => 1,
            'unit_cost' => 10,
            'reference' => 'test',
            'created_by' => $this->user->id,
        ]);
    }

    resetQueryLog();

    $response = $this->getJson('/api/v1/stock/movements');

    $response->assertOk();
    expect(queryCount())->toBeLessThan(15, 'Potential N+1 on stock movements list');
});

// ─── CRM Deals list (customer, stage, pipeline, assignee) ───────────

test('CRM deals list does not have N+1 queries', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'pipeline_id' => $pipeline->id,
    ]);

    CrmDeal::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
        'assigned_to' => $this->user->id,
    ]);

    resetQueryLog();

    $response = $this->getJson('/api/v1/crm/deals');

    $response->assertOk();
    expect(queryCount())->toBeLessThan(15, 'Potential N+1 on CRM deals list');
});

// ─── Service Calls list (customer, technician, driver) ──────────────

test('service calls list does not have N+1 queries', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    ServiceCall::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'technician_id' => $this->user->id,
    ]);

    resetQueryLog();

    $response = $this->getJson('/api/v1/service-calls');

    $response->assertOk();
    expect(queryCount())->toBeLessThan(15, 'Potential N+1 on service-calls list');
});

// ─── IAM Users list (roles) ────────────────────────────────────────

test('users list does not have N+1 queries', function () {
    // Create 20 users attached to the same tenant
    $users = User::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);

    // Attach each user to the tenant pivot
    $users->each(fn (User $u) => $u->tenants()->syncWithoutDetaching([$this->tenant->id]));

    resetQueryLog();

    $response = $this->getJson('/api/v1/users');

    $response->assertOk();
    expect(queryCount())->toBeLessThan(15, 'Potential N+1 on users list');
});

// ─── Dashboard stats (aggregate queries, no N+1 expected) ───────────

test('dashboard stats use aggregate queries without N+1', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->count(10)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    AccountReceivable::factory()->count(10)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    resetQueryLog();

    $response = $this->getJson('/api/v1/dashboard-stats');

    $response->assertOk();
    // Dashboard should use COUNT/SUM aggregates, not load individual records
    expect(queryCount())->toBeLessThan(20, 'Dashboard stats should use aggregate queries');
});

// ─── Scaling check: query count stays constant regardless of record count

test('customer query count stays constant between 5 and 50 records', function () {
    // Measure with 5 records
    Customer::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

    resetQueryLog();
    $this->getJson('/api/v1/customers');
    $queriesWith5 = queryCount();

    // Add 45 more records (total 50)
    Customer::factory()->count(45)->create(['tenant_id' => $this->tenant->id]);

    resetQueryLog();
    $this->getJson('/api/v1/customers');
    $queriesWith50 = queryCount();

    // In a well-optimized endpoint the query count should be similar
    // Allow some variance for framework overhead but not proportional growth
    $growth = $queriesWith50 - $queriesWith5;
    expect($growth)->toBeLessThan(5, "Query count grew by {$growth} between 5 and 50 records -- likely N+1");
});

test('work order query count stays constant between 5 and 50 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->count(5)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/work-orders');
    $queriesWith5 = queryCount();

    WorkOrder::factory()->count(45)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/work-orders');
    $queriesWith50 = queryCount();

    $growth = $queriesWith50 - $queriesWith5;
    expect($growth)->toBeLessThan(5, "Query count grew by {$growth} between 5 and 50 work orders -- likely N+1");
});

test('accounts receivable query count stays constant between 5 and 50 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    AccountReceivable::factory()->count(5)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/accounts-receivable');
    $queriesWith5 = queryCount();

    AccountReceivable::factory()->count(45)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/accounts-receivable');
    $queriesWith50 = queryCount();

    $growth = $queriesWith50 - $queriesWith5;
    expect($growth)->toBeLessThan(5, "Query count grew by {$growth} between 5 and 50 receivables -- likely N+1");
});

test('CRM deals query count stays constant between 5 and 50 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create(['pipeline_id' => $pipeline->id]);

    CrmDeal::factory()->count(5)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/crm/deals');
    $queriesWith5 = queryCount();

    CrmDeal::factory()->count(45)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/crm/deals');
    $queriesWith50 = queryCount();

    $growth = $queriesWith50 - $queriesWith5;
    expect($growth)->toBeLessThan(5, "Query count grew by {$growth} between 5 and 50 deals -- likely N+1");
});

test('equipment query count stays constant between 5 and 50 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    Equipment::factory()->count(5)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/equipments');
    $queriesWith5 = queryCount();

    Equipment::factory()->count(45)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/equipments');
    $queriesWith50 = queryCount();

    $growth = $queriesWith50 - $queriesWith5;
    expect($growth)->toBeLessThan(5, "Query count grew by {$growth} between 5 and 50 equipments -- likely N+1");
});

test('service calls query count stays constant between 5 and 50 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    ServiceCall::factory()->count(5)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/service-calls');
    $queriesWith5 = queryCount();

    ServiceCall::factory()->count(45)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/service-calls');
    $queriesWith50 = queryCount();

    $growth = $queriesWith50 - $queriesWith5;
    expect($growth)->toBeLessThan(5, "Query count grew by {$growth} between 5 and 50 service calls -- likely N+1");
});

test('quotes query count stays constant between 5 and 50 records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    Quote::factory()->count(5)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/quotes');
    $queriesWith5 = queryCount();

    Quote::factory()->count(45)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);

    resetQueryLog();
    $this->getJson('/api/v1/quotes');
    $queriesWith50 = queryCount();

    $growth = $queriesWith50 - $queriesWith5;
    expect($growth)->toBeLessThan(5, "Query count grew by {$growth} between 5 and 50 quotes -- likely N+1");
});
