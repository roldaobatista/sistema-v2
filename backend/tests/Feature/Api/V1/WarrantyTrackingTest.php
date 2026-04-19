<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WarrantyTracking;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarrantyTrackingTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Equipment $equipment;

    private Product $product;

    private WorkOrder $workOrder;

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

        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Warranty Product',
            'code' => 'WARR-001',
            'unit' => 'un',
            'stock_qty' => 0,
        ]);
        $this->workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $this->equipment->id,
            'number' => WorkOrder::nextNumber($this->tenant->id),
            'status' => WorkOrder::STATUS_OPEN,
            'priority' => WorkOrder::PRIORITY_MEDIUM,
            'description' => 'Warranty tracking test',
            'origin_type' => WorkOrder::ORIGIN_MANUAL,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);
    }

    public function test_index_returns_paginated_warranties(): void
    {
        WarrantyTracking::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $this->equipment->id,
            'product_id' => $this->product->id,
            'warranty_start_at' => now()->subMonths(3),
            'warranty_end_at' => now()->addMonths(9),
            'warranty_type' => 'part',
        ]);

        $response = $this->getJson('/api/v1/stock-advanced/warranty-tracking');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_index_filters_by_customer_id(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WarrantyTracking::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $this->equipment->id,
            'product_id' => $this->product->id,
            'warranty_start_at' => now()->subMonths(1),
            'warranty_end_at' => now()->addMonths(11),
            'warranty_type' => 'part',
        ]);
        WarrantyTracking::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'customer_id' => $otherCustomer->id,
            'equipment_id' => $this->equipment->id,
            'product_id' => $this->product->id,
            'warranty_start_at' => now()->subMonths(2),
            'warranty_end_at' => now()->addMonths(10),
            'warranty_type' => 'service',
        ]);

        $response = $this->getJson("/api/v1/stock-advanced/warranty-tracking?customer_id={$this->customer->id}");

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals($this->customer->id, $item['customer_id']);
        }
    }

    public function test_index_filters_by_equipment_id(): void
    {
        WarrantyTracking::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $this->equipment->id,
            'product_id' => $this->product->id,
            'warranty_start_at' => now()->subMonths(1),
            'warranty_end_at' => now()->addMonths(11),
            'warranty_type' => 'part',
        ]);

        $response = $this->getJson("/api/v1/stock-advanced/warranty-tracking?equipment_id={$this->equipment->id}");

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals($this->equipment->id, $item['equipment_id']);
        }
    }

    public function test_index_filter_status_active(): void
    {
        // Active warranty (ends in future)
        WarrantyTracking::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $this->equipment->id,
            'product_id' => $this->product->id,
            'warranty_start_at' => now()->subMonths(3),
            'warranty_end_at' => now()->addMonths(9),
            'warranty_type' => 'part',
        ]);
        // Expired warranty
        WarrantyTracking::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $this->equipment->id,
            'product_id' => $this->product->id,
            'warranty_start_at' => now()->subMonths(18),
            'warranty_end_at' => now()->subMonths(6),
            'warranty_type' => 'service',
        ]);

        $response = $this->getJson('/api/v1/stock-advanced/warranty-tracking?status=active');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertGreaterThanOrEqual(now()->toDateString(), $item['warranty_end_at']);
        }
    }

    public function test_index_filter_status_expired(): void
    {
        WarrantyTracking::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $this->equipment->id,
            'product_id' => $this->product->id,
            'warranty_start_at' => now()->subMonths(18),
            'warranty_end_at' => now()->subMonths(6),
            'warranty_type' => 'part',
        ]);

        $response = $this->getJson('/api/v1/stock-advanced/warranty-tracking?status=expired');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertLessThan(now()->toDateString(), $item['warranty_end_at']);
        }
    }

    public function test_index_filter_status_expiring(): void
    {
        // Expiring in 15 days (within 30-day window)
        WarrantyTracking::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $this->equipment->id,
            'product_id' => $this->product->id,
            'warranty_start_at' => now()->subMonths(11),
            'warranty_end_at' => now()->addDays(15),
            'warranty_type' => 'part',
        ]);

        $response = $this->getJson('/api/v1/stock-advanced/warranty-tracking?status=expiring');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($items));
    }

    public function test_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherEquipment = Equipment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $otherProduct = Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Product',
            'code' => 'OTP-001',
            'unit' => 'un',
            'stock_qty' => 0,
        ]);

        WarrantyTracking::create([
            'tenant_id' => $otherTenant->id,
            'work_order_id' => WorkOrder::factory()->create([
                'tenant_id' => $otherTenant->id,
                'customer_id' => $otherCustomer->id,
            ])->id,
            'customer_id' => $otherCustomer->id,
            'equipment_id' => $otherEquipment->id,
            'product_id' => $otherProduct->id,
            'warranty_start_at' => now()->subMonths(1),
            'warranty_end_at' => now()->addMonths(11),
            'warranty_type' => 'part',
        ]);

        $response = $this->getJson('/api/v1/stock-advanced/warranty-tracking');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        // Should not include warranties from other tenant
        foreach ($items as $item) {
            $this->assertEquals($this->tenant->id, $item['tenant_id']);
        }
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/stock-advanced/warranty-tracking');

        $response->assertUnauthorized();
    }
}
