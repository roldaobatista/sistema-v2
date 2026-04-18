<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarrantyTracking;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockAdvancedTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Warehouse $warehouse;

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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Armazém Principal',
            'code' => 'ARM-001',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);
    }

    // ── Serial Numbers ──────────────────────────────────────────

    public function test_serial_numbers_index_returns_paginated(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Serial',
            'code' => 'SER-001',
            'unit' => 'un',
            'stock_qty' => 10,
        ]);

        ProductSerial::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'serial_number' => 'SN-001',
            'status' => 'available',
        ]);

        $response = $this->getJson('/api/v1/stock/serial-numbers');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_store_serial_number_successfully(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Serial',
            'code' => 'SER-002',
            'unit' => 'un',
            'stock_qty' => 5,
        ]);

        $response = $this->postJson('/api/v1/stock/serial-numbers', [
            'product_id' => $product->id,
            'serial_number' => 'SN-UNIQUE-001',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'available',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('product_serials', [
            'serial_number' => 'SN-UNIQUE-001',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_serial_number_rejects_duplicate(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Dup',
            'code' => 'SER-DUP',
            'unit' => 'un',
            'stock_qty' => 5,
        ]);

        ProductSerial::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'serial_number' => 'SN-DUP-001',
            'status' => 'available',
        ]);

        $response = $this->postJson('/api/v1/stock/serial-numbers', [
            'product_id' => $product->id,
            'serial_number' => 'SN-DUP-001',
        ]);

        $response->assertStatus(422);
    }

    // ── Auto Reorder ────────────────────────────────────────────

    public function test_auto_reorder_returns_suggestions(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Baixo Estoque',
            'code' => 'LOW-001',
            'unit' => 'un',
            'stock_qty' => 5,
            'min_repo_point' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/stock-advanced/auto-reorder/suggestions');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('products_below_reorder', $data);
        $this->assertArrayHasKey('suggestions', $data);
        $this->assertGreaterThanOrEqual(1, $data['products_below_reorder']);
    }

    public function test_auto_reorder_excludes_products_with_pending_quotations(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Already Ordering',
            'code' => 'ORD-001',
            'unit' => 'un',
            'stock_qty' => 2,
            'min_repo_point' => 10,
            'is_active' => true,
        ]);

        // Create pending purchase quotation with item for this product
        $supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'PJ',
            'name' => 'Fornecedor Teste',
            'is_active' => true,
        ]);

        $pqId = DB::table('purchase_quotations')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'status' => 'pending',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('purchase_quotation_items')->insert([
            'purchase_quotation_id' => $pqId,
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_price' => 10.0,
            'total' => 200.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/stock-advanced/auto-reorder/suggestions');

        $response->assertOk();
        $data = $response->json('data');
        $productIds = collect($data['suggestions'])->pluck('product_id')->all();
        $this->assertNotContains($product->id, $productIds);
    }

    // ── Slow Moving Analysis ────────────────────────────────────

    public function test_slow_moving_analysis_returns_results(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Parado',
            'code' => 'SLOW-001',
            'unit' => 'un',
            'stock_qty' => 100,
            'cost_price' => 50.0,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/stock-advanced/slow-moving?days=90');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('period_days', $data);
        $this->assertArrayHasKey('slow_moving_count', $data);
        $this->assertArrayHasKey('total_capital_locked', $data);
        $this->assertArrayHasKey('products', $data);
    }

    public function test_slow_moving_custom_days_parameter(): void
    {
        $response = $this->getJson('/api/v1/stock-advanced/slow-moving?days=30');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(30, $data['period_days']);
    }

    // ── Warranty Lookup ─────────────────────────────────────────

    public function test_warranty_lookup_returns_warranties(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Warranty Product',
            'code' => 'WP-001',
            'unit' => 'un',
            'stock_qty' => 0,
        ]);
        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'number' => WorkOrder::nextNumber($this->tenant->id),
            'status' => WorkOrder::STATUS_OPEN,
            'priority' => WorkOrder::PRIORITY_MEDIUM,
            'description' => 'Warranty lookup test',
            'origin_type' => WorkOrder::ORIGIN_MANUAL,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);

        WarrantyTracking::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'equipment_id' => $equipment->id,
            'product_id' => $product->id,
            'customer_id' => $this->customer->id,
            'warranty_start_at' => now()->subMonths(3),
            'warranty_end_at' => now()->addMonths(9),
            'warranty_type' => 'part',
        ]);

        $response = $this->getJson("/api/v1/stock-advanced/warranty/lookup?equipment_id={$equipment->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('active', $data);
        $this->assertArrayHasKey('expired', $data);
        $this->assertGreaterThanOrEqual(1, $data['total']);
    }

    // ── Cyclic Count ────────────────────────────────────────────

    public function test_start_cyclic_count_creates_session(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Count Product',
            'code' => 'CNT-001',
            'unit' => 'un',
            'stock_qty' => 25,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/stock-advanced/inventory/start-count', [
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertArrayHasKey('count_id', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertGreaterThanOrEqual(1, $data['items']);
    }

    public function test_submit_count_records_quantities(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Submit Count Prod',
            'code' => 'SC-001',
            'unit' => 'un',
            'stock_qty' => 30,
            'is_active' => true,
        ]);

        $countId = DB::table('inventory_counts')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'in_progress',
            'started_by' => $this->user->id,
            'items_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventory_count_items')->insert([
            'inventory_count_id' => $countId,
            'product_id' => $product->id,
            'system_quantity' => 30,
            'counted_quantity' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/stock-advanced/inventory/{$countId}/submit", [
            'items' => [
                ['product_id' => $product->id, 'counted_quantity' => 28],
            ],
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('pending_items', $data);
        $this->assertArrayHasKey('divergences', $data);
        // There should be a divergence since 28 != 30
        $this->assertEquals(1, $data['divergences']);
    }

    public function test_submit_count_rejects_nonexistent_count(): void
    {
        $response = $this->postJson('/api/v1/stock-advanced/inventory/999999/submit', [
            'items' => [['product_id' => 1, 'counted_quantity' => 10]],
        ]);

        $response->assertStatus(404);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/stock-advanced/auto-reorder/suggestions');

        $response->assertUnauthorized();
    }
}
