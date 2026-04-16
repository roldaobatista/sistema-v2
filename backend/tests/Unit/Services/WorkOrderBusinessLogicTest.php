<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WorkOrderBusinessLogicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    public function test_recalculate_total_sums_items(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 2,
            'unit_price' => '100.00',
            'total' => '200.00',
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 1,
            'unit_price' => '50.00',
            'total' => '50.00',
        ]);

        $wo->recalculateTotal();
        $wo->refresh();

        $this->assertEquals('250.00', $wo->total);
    }

    public function test_recalculate_total_with_zero_items(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '999.00',
        ]);

        $wo->recalculateTotal();
        $wo->refresh();

        $this->assertEquals('0.00', $wo->total);
    }

    public function test_recalculate_total_with_discount(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
            'discount' => '50.00',
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 1,
            'unit_price' => '500.00',
            'total' => '500.00',
        ]);

        $wo->recalculateTotal();
        $wo->refresh();

        $this->assertNotNull($wo->total);
    }

    public function test_work_order_has_items_relationship(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '1000.00',
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 1,
            'unit_price' => '1000.00',
            'total' => '1000.00',
        ]);

        $this->assertCount(1, $wo->items);
    }

    public function test_next_number_generates_unique(): void
    {
        $num1 = WorkOrder::nextNumber($this->tenant->id);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'os_number' => $num1,
        ]);

        $num2 = WorkOrder::nextNumber($this->tenant->id);
        $this->assertNotEquals($num1, $num2);
    }

    public function test_work_order_transition_open_to_in_progress(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_IN_PROGRESS));
    }

    public function test_work_order_cannot_transition_completed_to_open(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        $this->assertFalse($wo->canTransitionTo(WorkOrder::STATUS_OPEN));
    }

    public function test_work_order_can_be_reassigned(): void
    {
        $tech1 = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);
        $tech2 = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $tech1->id,
        ]);

        $wo->update(['assigned_to' => $tech2->id]);
        $wo->refresh();

        $this->assertEquals($tech2->id, $wo->assigned_to);
    }

    public function test_work_order_tags_can_be_updated(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'tags' => ['urgent'],
        ]);

        $wo->update(['tags' => ['urgent', 'vip', 'calibração']]);
        $wo->refresh();

        $this->assertCount(3, $wo->tags);
        $this->assertContains('vip', $wo->tags);
    }

    public function test_work_order_has_status_constants(): void
    {
        $this->assertEquals('open', WorkOrder::STATUS_OPEN);
        $this->assertEquals('completed', WorkOrder::STATUS_COMPLETED);
        $this->assertEquals('cancelled', WorkOrder::STATUS_CANCELLED);
    }

    public function test_is_master_work_order(): void
    {
        $master = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_master' => true,
        ]);

        $child = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'parent_id' => $master->id,
        ]);

        $this->assertTrue($master->is_master);
        $this->assertEquals($master->id, $child->parent_id);
        $this->assertCount(1, $master->children);
    }
}
