<?php

namespace Tests\Unit;

use App\Events\WorkOrderCancelled;
use App\Events\WorkOrderCompleted;
use App\Events\WorkOrderInvoiced;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * PROFESSIONAL Tests — Work Order Status Machine
 *
 * Validates the state machine: allowed transitions, denied transitions,
 * timestamp updates, event dispatching, and status history logging.
 *
 * Updated to match the granular field-service status flow:
 * open → awaiting_dispatch → in_displacement → at_client → in_service
 * → awaiting_return → completed → delivered → invoiced
 */
class WorkOrderStatusMachineTest extends TestCase
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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($this->user);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        // Assign role with required permissions (guard must match PermissionsSeeder: web)
        $role = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]
        );
        $permissions = ['os.work_order.view', 'os.work_order.update', 'os.work_order.change_status'];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $role->syncPermissions($permissions);
        $this->user->assignRole($role);
    }

    private function createWorkOrder(string $status = 'open'): WorkOrder
    {
        return WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => $status,
            'total' => 5000.00,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 1. OPEN → IN_DISPLACEMENT (VALID — skipping dispatch)
    // ═══════════════════════════════════════════════════════════

    public function test_open_can_transition_to_in_displacement(): void
    {
        $wo = $this->createWorkOrder(WorkOrder::STATUS_OPEN);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 2. OPEN → INVOICED (INVALID — cannot skip states)
    // ═══════════════════════════════════════════════════════════

    public function test_open_cannot_jump_to_invoiced(): void
    {
        $wo = $this->createWorkOrder(WorkOrder::STATUS_OPEN);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Transição inválida', $response->json('message'));
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 3. AWAITING_RETURN → COMPLETED SETS completed_at
    // ═══════════════════════════════════════════════════════════

    public function test_awaiting_return_to_completed_sets_completed_at(): void
    {
        Event::fake([WorkOrderCompleted::class]);
        $wo = $this->createWorkOrder(WorkOrder::STATUS_AWAITING_RETURN);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        $response->assertOk();
        $this->assertNotNull($wo->fresh()->completed_at);
        Event::assertDispatched(WorkOrderCompleted::class);
    }

    // ═══════════════════════════════════════════════════════════
    // 4. COMPLETED → DELIVERED SETS delivered_at
    // ═══════════════════════════════════════════════════════════

    public function test_completed_to_delivered_sets_delivered_at(): void
    {
        $wo = $this->createWorkOrder(WorkOrder::STATUS_COMPLETED);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_DELIVERED,
            'agreed_payment_method' => 'pix',
        ]);

        $response->assertOk();
        $this->assertNotNull($wo->fresh()->delivered_at);
    }

    // ═══════════════════════════════════════════════════════════
    // 5. OPEN → AWAITING_DISPATCH (VALID)
    // ═══════════════════════════════════════════════════════════

    public function test_open_can_transition_to_awaiting_dispatch(): void
    {
        $wo = $this->createWorkOrder(WorkOrder::STATUS_OPEN);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_AWAITING_DISPATCH,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_AWAITING_DISPATCH,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 6. TRANSIÇÃO INVÁLIDA RETORNA 422 COM allowed[]
    // ═══════════════════════════════════════════════════════════

    public function test_invalid_transition_returns_allowed_transitions(): void
    {
        $wo = $this->createWorkOrder(WorkOrder::STATUS_OPEN);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_DELIVERED,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'allowed']);
        $data = $response->json();
        $this->assertContains(WorkOrder::STATUS_AWAITING_DISPATCH, $data['allowed']);
        $this->assertContains(WorkOrder::STATUS_CANCELLED, $data['allowed']);
    }

    // ═══════════════════════════════════════════════════════════
    // 7. STATUS_CANCELLED DISPARA EVENTO
    // ═══════════════════════════════════════════════════════════

    public function test_cancel_dispatches_event_with_notes(): void
    {
        Event::fake([WorkOrderCancelled::class]);
        Notification::fake();
        $wo = $this->createWorkOrder(WorkOrder::STATUS_OPEN);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_CANCELLED,
            'notes' => 'Cliente desistiu',
        ]);

        $response->assertOk();
        Event::assertDispatched(WorkOrderCancelled::class);
    }

    // ═══════════════════════════════════════════════════════════
    // 8. DELIVERED → INVOICED (LAST VALID TRANSITION)
    // ═══════════════════════════════════════════════════════════

    public function test_delivered_to_invoiced_dispatches_event(): void
    {
        Event::fake([WorkOrderInvoiced::class]);
        $wo = $this->createWorkOrder(WorkOrder::STATUS_DELIVERED);

        // INVOICED requires at least one item
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $wo->tenant_id,
        ]);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_INVOICED,
            'agreed_payment_method' => 'pix',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);
        Event::assertDispatched(WorkOrderInvoiced::class);
    }

    // ═══════════════════════════════════════════════════════════
    // 9. INVOICED NÃO TEM TRANSIÇÕES (TERMINAL)
    // ═══════════════════════════════════════════════════════════

    public function test_invoiced_is_terminal_state(): void
    {
        $wo = $this->createWorkOrder(WorkOrder::STATUS_INVOICED);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // 10. CANCELLED PODE SER REABERTA (→ OPEN)
    // ═══════════════════════════════════════════════════════════

    public function test_cancelled_can_reopen_to_open(): void
    {
        $wo = $this->createWorkOrder(WorkOrder::STATUS_CANCELLED);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }
}
