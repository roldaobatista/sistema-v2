<?php

namespace Tests\Feature\Api\V1\Os;

use App\Events\WorkOrderCompleted;
use App\Listeners\HandleWorkOrderCompletion;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderCompletionListenerTest extends TestCase
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

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function test_event_is_dispatched(): void
    {
        Event::fake([WorkOrderCompleted::class]);

        event(new WorkOrderCompleted($this->workOrder, $this->user, 'in_service'));

        Event::assertDispatched(WorkOrderCompleted::class, function ($event) {
            return $event->workOrder->id === $this->workOrder->id;
        });
    }

    public function test_listener_handles_without_errors(): void
    {
        Notification::fake();

        $listener = app(HandleWorkOrderCompletion::class);
        $event = new WorkOrderCompleted($this->workOrder, $this->user, 'in_service');

        $listener->handle($event);

        $this->assertTrue(true, 'Listener executed without throwing');
    }

    public function test_listener_recalculates_customer_health_score(): void
    {
        Notification::fake();

        $listener = app(HandleWorkOrderCompletion::class);
        $event = new WorkOrderCompleted($this->workOrder, $this->user, 'in_service');
        $listener->handle($event);

        $this->customer->refresh();
        $this->assertNotNull($this->customer->health_score);
    }

    public function test_listener_tolerates_work_order_without_customer(): void
    {
        Notification::fake();

        // Simulate a deleted or missing customer by unsetting the relation
        // without hitting the database (avoids SQLite NOT NULL constraint)
        $this->workOrder->setRelation('customer', null);

        $listener = app(HandleWorkOrderCompletion::class);
        $event = new WorkOrderCompleted($this->workOrder, $this->user, 'in_service');

        $listener->handle($event);

        $this->assertTrue(true, 'Listener handled missing customer gracefully');
    }
}
