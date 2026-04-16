<?php

namespace Tests\Feature\Api\V1\Os;

use App\Enums\FinancialStatus;
use App\Enums\InvoiceStatus;
use App\Events\WorkOrderCancelled;
use App\Listeners\HandleWorkOrderCancellation;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderCancellationListenerTest extends TestCase
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

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Teste cancelamento',
        ]);
    }

    public function test_event_is_dispatched(): void
    {
        Event::fake([WorkOrderCancelled::class]);

        event(new WorkOrderCancelled($this->workOrder, $this->user, 'Motivo teste', 'in_service'));

        Event::assertDispatched(WorkOrderCancelled::class, function ($event) {
            return $event->workOrder->id === $this->workOrder->id
                && $event->reason === 'Motivo teste';
        });
    }

    public function test_listener_handles_without_errors(): void
    {
        $listener = app(HandleWorkOrderCancellation::class);
        $event = new WorkOrderCancelled($this->workOrder, $this->user, 'Teste', 'invoiced');

        $listener->handle($event);

        $this->assertTrue(true, 'Listener executed without throwing');
    }

    public function test_listener_cancels_related_invoices(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'status' => InvoiceStatus::DRAFT,
        ]);

        $listener = app(HandleWorkOrderCancellation::class);
        $event = new WorkOrderCancelled($this->workOrder, $this->user, 'Teste', 'invoiced');
        $listener->handle($event);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::CANCELLED, $invoice->status);
    }

    public function test_listener_cancels_related_accounts_receivable(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'status' => FinancialStatus::PENDING,
        ]);

        $listener = app(HandleWorkOrderCancellation::class);
        $event = new WorkOrderCancelled($this->workOrder, $this->user, 'Teste', 'invoiced');
        $listener->handle($event);

        $ar->refresh();
        $this->assertEquals(FinancialStatus::CANCELLED, $ar->status);
    }

    public function test_listener_is_idempotent(): void
    {
        $listener = app(HandleWorkOrderCancellation::class);
        $event = new WorkOrderCancelled($this->workOrder, $this->user, 'Teste', 'completed');

        $listener->handle($event);
        $listener->handle($event);

        $this->assertTrue(true, 'Listener handled double execution gracefully');
    }
}
