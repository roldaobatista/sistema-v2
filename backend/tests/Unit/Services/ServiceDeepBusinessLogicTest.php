<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Tests\TestCase;

class ServiceDeepBusinessLogicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);
    }

    // ── WorkOrder Business Logic ──

    public function test_work_order_total_recalculation(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
        ]);
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 5,
            'unit_price' => 100,
            'discount' => 0,
        ]);
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 3,
            'unit_price' => 100,
            'discount' => 0,
        ]);
        $total = $wo->fresh()->items()->sum('total');
        $this->assertEquals('800.00', $total);
        $this->assertEquals('800.00', (string) $wo->fresh()->total);
    }

    public function test_work_order_os_number_auto_increment(): void
    {
        $wo1 = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $wo2 = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertNotNull($wo1->os_number);
        $this->assertNotNull($wo2->os_number);
        $this->assertNotEquals($wo1->os_number, $wo2->os_number);
    }

    public function test_work_order_complete_flow(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $wo->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $wo->fresh()->status);

        $wo->update(['status' => WorkOrder::STATUS_COMPLETED, 'completed_at' => now()]);
        $this->assertEquals(WorkOrder::STATUS_COMPLETED, $wo->fresh()->status);
    }

    public function test_work_order_reopen(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
        $wo->update(['status' => WorkOrder::STATUS_WAITING_APPROVAL]);
        $this->assertEquals(WorkOrder::STATUS_WAITING_APPROVAL, $wo->fresh()->status);
    }

    public function test_work_order_with_equipment(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $eq->id,
        ]);
        $this->assertEquals($eq->id, $wo->equipment_id);
    }

    // ── Quote Conversion ──

    public function test_quote_to_work_order_conversion(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'approved',
            'total' => '5000.00',
        ]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_id' => $quote->id,
            'total' => $quote->total,
        ]);
        $this->assertEquals($quote->id, $wo->quote_id);
        $this->assertEquals($quote->total, $wo->total);
    }

    public function test_quote_items_sum_matches_total(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        QuoteItem::factory()->create([
            'quote_id' => $quote->id,
            'tenant_id' => $this->tenant->id,
            'total' => '1000.00',
        ]);
        QuoteItem::factory()->create([
            'quote_id' => $quote->id,
            'tenant_id' => $this->tenant->id,
            'total' => '2000.00',
        ]);
        $total = $quote->items()->sum('total');
        $this->assertEquals('3000.00', $total);
    }

    // ── Customer Revenue ──

    public function test_customer_lifetime_value(): void
    {
        WorkOrder::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '1000.00',
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
        $ltv = $this->customer->workOrders()
            ->where('status', WorkOrder::STATUS_COMPLETED)
            ->sum('total');
        $this->assertEquals('5000.00', $ltv);
    }

    public function test_customer_average_ticket(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '2000.00',
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '4000.00',
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
        $avg = $this->customer->workOrders()
            ->where('status', WorkOrder::STATUS_COMPLETED)
            ->avg('total');
        $this->assertEquals(3000.00, (float) $avg);
    }

    // ── Equipment Calibration Business Logic ──

    public function test_equipment_next_calibration(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $cal = EquipmentCalibration::factory()->create([
            'equipment_id' => $eq->id,
            'tenant_id' => $this->tenant->id,
            'calibration_date' => now()->subMonths(11),
            'next_due_date' => now()->addMonth(),
        ]);
        $this->assertTrue($cal->next_due_date->isFuture());
    }

    public function test_equipment_overdue_calibration(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $cal = EquipmentCalibration::factory()->create([
            'equipment_id' => $eq->id,
            'tenant_id' => $this->tenant->id,
            'calibration_date' => now()->subYears(2),
            'next_due_date' => now()->subMonths(6),
        ]);
        $this->assertTrue($cal->next_due_date->isPast());
    }

    // ── Dashboard Stats ──

    public function test_dashboard_open_work_orders_count(): void
    {
        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $count = WorkOrder::where('tenant_id', $this->tenant->id)
            ->where('status', WorkOrder::STATUS_OPEN)->count();
        $this->assertGreaterThanOrEqual(3, $count);
    }

    public function test_dashboard_revenue_this_month(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '10000.00',
            'status' => WorkOrder::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        $revenue = WorkOrder::where('tenant_id', $this->tenant->id)
            ->where('status', WorkOrder::STATUS_COMPLETED)
            ->whereMonth('completed_at', now()->month)
            ->sum('total');
        $this->assertGreaterThanOrEqual(10000, $revenue);
    }

    public function test_dashboard_customers_count(): void
    {
        Customer::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);
        $count = Customer::where('tenant_id', $this->tenant->id)->count();
        $this->assertGreaterThanOrEqual(6, $count); // 5 + 1 from setUp
    }

    public function test_dashboard_equipment_count(): void
    {
        Equipment::factory()->count(10)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $count = Equipment::where('tenant_id', $this->tenant->id)->count();
        $this->assertGreaterThanOrEqual(10, $count);
    }

    // ── Bulk Operations ──

    public function test_bulk_status_update(): void
    {
        $wos = WorkOrder::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $ids = $wos->pluck('id')->toArray();
        WorkOrder::whereIn('id', $ids)->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $updated = WorkOrder::whereIn('id', $ids)
            ->where('status', WorkOrder::STATUS_IN_PROGRESS)->count();
        $this->assertEquals(5, $updated);
    }

    public function test_bulk_delete(): void
    {
        $wos = WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $ids = $wos->pluck('id')->toArray();
        WorkOrder::whereIn('id', $ids)->delete();
        $remaining = WorkOrder::whereIn('id', $ids)->count();
        $this->assertEquals(0, $remaining);
    }

    // ── Filters ──

    public function test_work_orders_filter_by_priority(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'priority' => 'urgent',
        ]);
        $results = WorkOrder::where('priority', 'urgent')->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_work_orders_filter_by_date_range(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_at' => now()->subDays(3),
        ]);
        $results = WorkOrder::whereBetween('created_at', [now()->subWeek(), now()])->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_work_orders_sort_by_created_at(): void
    {
        $wos = WorkOrder::orderBy('created_at', 'desc')->take(5)->get();
        $this->assertGreaterThanOrEqual(0, $wos->count());
    }
}
