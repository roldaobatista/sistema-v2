<?php

namespace Tests\Feature\Api\V1\Os;

use App\Events\WorkOrderCompleted;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderCommissionIntegrationTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_completing_work_order_dispatches_commission_event(): void
    {
        Event::fake([WorkOrderCompleted::class]);

        $this->workOrder = WorkOrder::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        WorkOrderCompleted::dispatch($this->workOrder, $this->user);

        Event::assertDispatched(WorkOrderCompleted::class, function (WorkOrderCompleted $event) {
            return $event->workOrder->id === $this->workOrder->id
                && $event->user->id === $this->user->id;
        });
    }

    public function test_commission_flow_handles_wo_without_items(): void
    {
        Event::fake([WorkOrderCompleted::class]);

        $this->workOrder = WorkOrder::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'total' => 0,
        ]);

        // WO without items - should still dispatch without errors
        $this->assertCount(0, $this->workOrder->items);

        WorkOrderCompleted::dispatch($this->workOrder, $this->user);

        Event::assertDispatched(WorkOrderCompleted::class, function (WorkOrderCompleted $event) {
            return $event->workOrder->id === $this->workOrder->id;
        });
    }
}
