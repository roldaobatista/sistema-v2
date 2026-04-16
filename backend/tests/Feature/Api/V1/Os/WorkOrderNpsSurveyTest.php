<?php

namespace Tests\Feature\Api\V1\Os;

use App\Events\WorkOrderCompleted;
use App\Listeners\TriggerNpsSurvey;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderNpsSurveyTest extends TestCase
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
            'email' => 'customer@example.com',
        ]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function test_listener_handles_without_errors(): void
    {
        Notification::fake();

        $listener = new TriggerNpsSurvey;
        $event = new WorkOrderCompleted($this->workOrder, $this->user, 'in_service');

        $listener->handle($event);

        $this->assertTrue(true, 'Listener executed without throwing');
    }

    public function test_listener_skips_when_customer_has_no_email(): void
    {
        Notification::fake();

        $this->customer->update(['email' => null]);
        $this->customer->refresh();

        $listener = new TriggerNpsSurvey;
        $event = new WorkOrderCompleted($this->workOrder, $this->user, 'in_service');

        $listener->handle($event);

        Notification::assertNothingSent();
    }

    public function test_listener_skips_when_no_customer(): void
    {
        Notification::fake();

        $this->workOrder->setRelation('customer', null);

        $listener = new TriggerNpsSurvey;
        $event = new WorkOrderCompleted($this->workOrder, $this->user, 'in_service');

        $listener->handle($event);

        Notification::assertNothingSent();
    }
}
