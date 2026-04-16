<?php

namespace Tests\Feature\Api\V1\Os;

use App\Events\WorkOrderCompleted;
use App\Events\WorkOrderInvoiced;
use App\Listeners\CreateWarrantyTrackingOnWorkOrderInvoiced;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WarrantyTracking;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderWarrantyTrackingTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private WorkOrder $workOrder;

    private WorkOrderItem $productItem;

    private WorkOrderItem $serviceItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->productItem = WorkOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => WorkOrderItem::TYPE_PRODUCT,
            'reference_id' => 1,
        ]);

        $this->serviceItem = WorkOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => WorkOrderItem::TYPE_SERVICE,
        ]);
    }

    public function test_creates_warranty_for_product_items_on_completion(): void
    {
        $listener = app(CreateWarrantyTrackingOnWorkOrderInvoiced::class);
        $event = new WorkOrderCompleted($this->workOrder, $this->user, 'in_service');

        $listener->handleWorkOrderCompleted($event);

        $this->assertDatabaseHas('warranty_tracking', [
            'work_order_id' => $this->workOrder->id,
            'work_order_item_id' => $this->productItem->id,
        ]);
    }

    public function test_does_not_create_warranty_for_service_items(): void
    {
        $listener = app(CreateWarrantyTrackingOnWorkOrderInvoiced::class);
        $event = new WorkOrderCompleted($this->workOrder, $this->user, 'in_service');

        $listener->handleWorkOrderCompleted($event);

        $this->assertDatabaseMissing('warranty_tracking', [
            'work_order_item_id' => $this->serviceItem->id,
        ]);
    }

    public function test_does_not_duplicate_warranty_when_both_events_fire(): void
    {
        $listener = app(CreateWarrantyTrackingOnWorkOrderInvoiced::class);

        $completedEvent = new WorkOrderCompleted($this->workOrder, $this->user, 'in_service');
        $listener->handleWorkOrderCompleted($completedEvent);

        $this->workOrder->updateQuietly(['status' => WorkOrder::STATUS_INVOICED]);
        $invoicedEvent = new WorkOrderInvoiced($this->workOrder, $this->user, 'completed');
        $listener->handleWorkOrderInvoiced($invoicedEvent);

        $count = WarrantyTracking::where('work_order_id', $this->workOrder->id)->count();
        $this->assertEquals(1, $count, 'firstOrCreate should prevent duplication');
    }

    public function test_warranty_dates_are_set_correctly(): void
    {
        $listener = app(CreateWarrantyTrackingOnWorkOrderInvoiced::class);
        $event = new WorkOrderCompleted($this->workOrder, $this->user, 'in_service');

        $listener->handleWorkOrderCompleted($event);

        $warranty = WarrantyTracking::where('work_order_item_id', $this->productItem->id)->first();
        $this->assertNotNull($warranty);
        $this->assertNotNull($warranty->warranty_start_at);
        $this->assertNotNull($warranty->warranty_end_at);
        $this->assertTrue(
            $warranty->warranty_end_at->greaterThan($warranty->warranty_start_at),
            'End date must be after start date'
        );
    }
}
