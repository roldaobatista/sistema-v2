<?php

namespace Tests\Feature\Api\V1\Os;

use App\Events\FiscalNoteAuthorized;
use App\Listeners\ReleaseWorkOrderOnFiscalNoteAuthorized;
use App\Models\FiscalNote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderFiscalNoteReleaseTest extends TestCase
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
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => config('services.fiscal.system_user_email', 'sistema@localhost'),
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_listener_handles_without_errors(): void
    {
        $this->workOrder = WorkOrder::factory()->delivered()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $fiscalNote = FiscalNote::factory()->authorized()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->workOrder->customer_id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $event = new FiscalNoteAuthorized($fiscalNote);
        $listener = app(ReleaseWorkOrderOnFiscalNoteAuthorized::class);

        // Should not throw any exception
        $listener->handle($event);

        $this->workOrder->refresh();

        $this->assertNotNull($this->workOrder);
    }

    public function test_listener_skips_when_wo_not_delivered(): void
    {
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $fiscalNote = FiscalNote::factory()->authorized()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->workOrder->customer_id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $event = new FiscalNoteAuthorized($fiscalNote);
        $listener = app(ReleaseWorkOrderOnFiscalNoteAuthorized::class);

        $listener->handle($event);

        $this->workOrder->refresh();

        $this->assertEquals(
            WorkOrder::STATUS_OPEN,
            $this->workOrder->status,
            'O status da OS não deve ser alterado quando não está como entregue.'
        );
    }

    public function test_listener_changes_status_to_invoiced(): void
    {
        $this->workOrder = WorkOrder::factory()->delivered()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $fiscalNote = FiscalNote::factory()->authorized()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->workOrder->customer_id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $event = new FiscalNoteAuthorized($fiscalNote);
        $listener = app(ReleaseWorkOrderOnFiscalNoteAuthorized::class);

        $listener->handle($event);

        $this->workOrder->refresh();

        $this->assertEquals(
            WorkOrder::STATUS_INVOICED,
            $this->workOrder->status,
            'A OS deve mudar para status faturada após nota fiscal autorizada.'
        );

        $this->assertDatabaseHas('work_order_status_history', [
            'work_order_id' => $this->workOrder->id,
            'from_status' => WorkOrder::STATUS_DELIVERED,
            'to_status' => WorkOrder::STATUS_INVOICED,
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
