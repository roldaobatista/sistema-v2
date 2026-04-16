<?php

namespace Tests\Feature\Api\V1\Os;

use App\Events\WorkOrderStarted;
use App\Listeners\LogWorkOrderStartActivity;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderStartListenerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private WorkOrder $workOrder;

    private Customer $customer;

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

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }

    public function test_listener_handles_without_errors(): void
    {
        $listener = new LogWorkOrderStartActivity;
        $event = new WorkOrderStarted($this->workOrder, $this->user, 'open');

        $listener->handle($event);

        $this->assertTrue(true, 'Listener executed without throwing');
    }

    public function test_listener_creates_status_history_record(): void
    {
        $listener = new LogWorkOrderStartActivity;
        $event = new WorkOrderStarted($this->workOrder, $this->user, 'open');

        $listener->handle($event);

        $this->assertDatabaseHas('work_order_status_history', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'from_status' => 'open',
            'to_status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_listener_handles_wo_without_assigned_to(): void
    {
        $this->workOrder->update(['assigned_to' => null]);
        $this->workOrder->refresh();

        $listener = new LogWorkOrderStartActivity;
        $event = new WorkOrderStarted($this->workOrder, $this->user, 'open');

        $listener->handle($event);

        $this->assertDatabaseHas('work_order_status_history', [
            'tenant_id' => $this->tenant->id,
            'from_status' => 'open',
            'to_status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
    }
}
