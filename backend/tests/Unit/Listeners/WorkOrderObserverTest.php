<?php

namespace Tests\Unit\Listeners;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Tests\TestCase;

class WorkOrderObserverTest extends TestCase
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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    public function test_creating_work_order_generates_os_number(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertNotNull($wo->os_number);
        $this->assertNotEmpty($wo->os_number);
    }

    public function test_creating_work_order_sets_created_by(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertNotNull($wo->created_by);
    }

    public function test_updating_work_order_status_validates_transition_and_updates(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $wo->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        $wo->refresh();
        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $wo->status);
    }

    public function test_completing_work_order_sets_completed_at(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);

        $wo->update(['status' => WorkOrder::STATUS_COMPLETED]);

        $wo->refresh();
        $this->assertNotNull($wo->completed_at);
    }

    public function test_cancelling_work_order_sets_cancelled_at(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $wo->update(['status' => WorkOrder::STATUS_CANCELLED]);

        $wo->refresh();
        $this->assertNotNull($wo->cancelled_at);
    }

    public function test_deleting_work_order_soft_deletes(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $wo->delete();

        $this->assertSoftDeleted('work_orders', ['id' => $wo->id]);
    }
}
