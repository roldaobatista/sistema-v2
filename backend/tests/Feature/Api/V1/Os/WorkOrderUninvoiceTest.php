<?php

namespace Tests\Feature\Api\V1\Os;

use App\Enums\FiscalNoteStatus;
use App\Enums\InvoiceStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\Customer;
use App\Models\FiscalNote;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderUninvoiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
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
            'status' => WorkOrder::STATUS_INVOICED,
        ]);
    }

    public function test_uninvoice_reverts_status_to_delivered(): void
    {
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'status' => InvoiceStatus::ISSUED,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/uninvoice");

        $response->assertOk();
        $this->assertDatabaseHas('work_orders', [
            'id' => $this->workOrder->id,
            'status' => WorkOrder::STATUS_DELIVERED,
        ]);
    }

    public function test_uninvoice_only_works_on_invoiced_status(): void
    {
        $this->workOrder->updateQuietly(['status' => WorkOrder::STATUS_OPEN]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/uninvoice");

        $response->assertStatus(422);
    }

    public function test_uninvoice_blocked_when_ar_has_payments(): void
    {
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'amount_paid' => 100.00,
            'status' => 'partial',
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/uninvoice");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Não é possível desfaturar — existem pagamentos já realizados nos títulos. Estorne os pagamentos primeiro.']);
    }

    public function test_uninvoice_blocked_when_fiscal_note_authorized(): void
    {
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        FiscalNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'status' => FiscalNoteStatus::AUTHORIZED,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/uninvoice");

        $response->assertStatus(422)
            ->assertSee('Nota Fiscal');
    }

    public function test_uninvoice_creates_status_history(): void
    {
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/uninvoice");

        $this->assertDatabaseHas('work_order_status_history', [
            'work_order_id' => $this->workOrder->id,
            'from_status' => WorkOrder::STATUS_INVOICED,
            'to_status' => WorkOrder::STATUS_DELIVERED,
        ]);
    }

    public function test_uninvoice_reverses_pending_commissions(): void
    {
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'status' => CommissionEvent::STATUS_PENDING,
            'notes' => 'trigger:os_invoiced',
        ]);

        $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/uninvoice");

        $this->assertDatabaseHas('commission_events', [
            'work_order_id' => $this->workOrder->id,
            'status' => CommissionEvent::STATUS_REVERSED,
        ]);
    }
}
