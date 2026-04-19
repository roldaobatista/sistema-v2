<?php

namespace Tests\Feature;

use App\Enums\ServiceCallStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Deep audit tests — Chamados (ServiceCall) + Ordens de Serviço (WorkOrder).
 *
 * Covers: tenant isolation, CRUD, status machines, validação, deleção com
 * dependências, duplicação, reabertura e estruturas de KPI/estatísticas.
 */
class ChamadosOsDeepAuditTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    private Customer $customerA;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenantA = Tenant::factory()->create(['name' => 'OSTenantA', 'status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['name' => 'OSTenantB', 'status' => 'active']);

        $this->adminA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'email' => 'admin@os-a.test',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->adminA->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        $this->adminB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'email' => 'admin@os-b.test',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->adminB->tenants()->attach($this->tenantB->id, ['is_default' => true]);

        $this->customerA = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

        app()->instance('current_tenant_id', $this->tenantA->id);
    }

    // ══════════════════════════════════════════════════════════════
    // ── SERVICE CALLS (CHAMADOS) — SC-01 a SC-13
    // ══════════════════════════════════════════════════════════════

    /** SC-01: Unauthenticated request must return 401 */
    public function test_sc_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/service-calls')->assertUnauthorized();
    }

    /** SC-02: Index only returns calls belonging to the authenticated user's tenant */
    public function test_sc_list_only_own_tenant_calls(): void
    {
        ServiceCall::factory(3)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);
        ServiceCall::factory(2)->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson('/api/v1/service-calls')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /** SC-03: Store without customer_id must fail validation */
    public function test_sc_store_requires_customer_id(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/service-calls', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    /** SC-04: Store with a customer from another tenant must fail */
    public function test_sc_store_with_other_tenant_customer_fails(): void
    {
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/service-calls', ['customer_id' => $customerB->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    /** SC-05: Successful store always creates with STATUS_OPEN regardless of payload */
    public function test_sc_store_creates_with_open_status(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->postJson('/api/v1/service-calls', [
            'customer_id' => $this->customerA->id,
            'priority' => 'medium',
            'observations' => 'Verificação de balança analógica',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('service_calls', [
            'customer_id' => $this->customerA->id,
            'tenant_id' => $this->tenantA->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);
    }

    /** SC-06: Show of a call from another tenant must return 404 */
    public function test_sc_show_cross_tenant_returns_404(): void
    {
        $callB = ServiceCall::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson("/api/v1/service-calls/{$callB->id}")
            ->assertNotFound();
    }

    /** SC-07: Valid status transition pending_scheduling → cancelled must succeed */
    public function test_sc_status_open_to_cancelled_succeeds(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->putJson("/api/v1/service-calls/{$call->id}/status", [
            'status' => ServiceCallStatus::CANCELLED->value,
        ])->assertOk();

        $this->assertDatabaseHas('service_calls', [
            'id' => $call->id,
            'status' => ServiceCallStatus::CANCELLED->value,
        ]);
    }

    /** SC-08: Invalid transition (pending_scheduling → converted_to_os) must return 422 */
    public function test_sc_status_invalid_transition_returns_422(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->putJson("/api/v1/service-calls/{$call->id}/status", [
            'status' => ServiceCallStatus::CONVERTED_TO_OS->value,
        ])->assertUnprocessable();

        // Status must remain unchanged
        $this->assertDatabaseHas('service_calls', [
            'id' => $call->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);
    }

    /** SC-09: Transitioning to scheduled without a technician must be blocked */
    public function test_sc_status_scheduled_without_technician_blocked(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
            'technician_id' => null,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->putJson("/api/v1/service-calls/{$call->id}/status", [
            'status' => ServiceCallStatus::SCHEDULED->value,
        ])->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Não é possível agendar um chamado sem técnico atribuído.']);
    }

    /** SC-10: Deleting a pending call must soft-delete it (204) */
    public function test_sc_delete_open_call_succeeds(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->deleteJson("/api/v1/service-calls/{$call->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('service_calls', ['id' => $call->id]);
    }

    /** SC-11: Deleting a call that has a linked WorkOrder must return 409 */
    public function test_sc_delete_with_linked_work_order_fails(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'service_call_id' => $call->id,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->deleteJson("/api/v1/service-calls/{$call->id}")
            ->assertConflict(); // 409
    }

    /** SC-12: Summary endpoint has the expected KPI keys */
    public function test_sc_summary_has_correct_structure(): void
    {
        ServiceCall::factory(2)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson('/api/v1/service-calls-summary')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'pending_scheduling',
                'scheduled',
                'rescheduled',
                'awaiting_confirmation',
                'converted_today',
                'sla_breached_active',
            ]]);
    }

    /** SC-13: KPI dashboard endpoint returns 200 */
    public function test_sc_kpi_dashboard_returns_200(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson('/api/v1/service-calls-kpi')
            ->assertOk();
    }

    // ══════════════════════════════════════════════════════════════
    // ── WORK ORDERS (OS) — WO-01 a WO-21
    // ══════════════════════════════════════════════════════════════

    /** WO-01: Unauthenticated request must return 401 */
    public function test_wo_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/work-orders')->assertUnauthorized();
    }

    /** WO-02: Index only returns orders belonging to the authenticated user's tenant */
    public function test_wo_list_only_own_tenant_orders(): void
    {
        WorkOrder::factory(4)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);
        WorkOrder::factory(2)->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson('/api/v1/work-orders')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    /** WO-03: Store without required fields must fail with specific validation errors */
    public function test_wo_store_requires_customer_and_description(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/work-orders', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'description']);
    }

    /** WO-04: Store with a customer from another tenant must fail */
    public function test_wo_store_with_other_tenant_customer_fails(): void
    {
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/work-orders', [
            'customer_id' => $customerB->id,
            'description' => 'Manutenção balança rodoviária',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    /** WO-05: Successful store creates with STATUS_OPEN and correct tenant */
    public function test_wo_store_creates_with_open_status(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customerA->id,
            'description' => 'Manutenção preventiva balança rodoviária',
            'priority' => 'medium',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('work_orders', [
            'customer_id' => $this->customerA->id,
            'tenant_id' => $this->tenantA->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }

    /** WO-06: Show of an order from another tenant must return 404 */
    public function test_wo_show_cross_tenant_returns_404(): void
    {
        $orderB = WorkOrder::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson("/api/v1/work-orders/{$orderB->id}")
            ->assertNotFound();
    }

    /** WO-07: Valid transition open → in_displacement must update DB */
    public function test_wo_status_valid_transition_open_to_in_progress(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson("/api/v1/work-orders/{$order->id}/status", [
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ])->assertOk();

        $this->assertDatabaseHas('work_orders', [
            'id' => $order->id,
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);
    }

    /** WO-08: Invalid transition (open → completed) must return 422 and leave status unchanged */
    public function test_wo_status_invalid_transition_returns_422(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson("/api/v1/work-orders/{$order->id}/status", [
            'status' => WorkOrder::STATUS_COMPLETED,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('work_orders', [
            'id' => $order->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }

    /** WO-09: Invoiced OS has no allowed transitions — any attempt must return 422 */
    public function test_wo_status_invoiced_has_no_allowed_transitions(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson("/api/v1/work-orders/{$order->id}/status", [
            'status' => WorkOrder::STATUS_OPEN,
        ])->assertUnprocessable();
    }

    /** WO-10: Adding an item to a work order must create it in DB */
    public function test_wo_add_item_to_order(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson("/api/v1/work-orders/{$order->id}/items", [
            'type' => 'service',
            'description' => 'Calibração de balança analógica',
            'quantity' => 1,
            'unit_price' => 180.00,
        ])->assertCreated();

        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => $order->id,
            'description' => 'Calibração de balança analógica',
        ]);
    }

    /** WO-11: Removing an item must soft-delete it (204) */
    public function test_wo_remove_item_from_order(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        $item = WorkOrderItem::create([
            'tenant_id' => $this->tenantA->id,
            'work_order_id' => $order->id,
            'type' => WorkOrderItem::TYPE_SERVICE,
            'description' => 'Serviço de teste',
            'quantity' => 1,
            'unit_price' => 100.00,
            'total' => 100.00,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->deleteJson("/api/v1/work-orders/{$order->id}/items/{$item->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('work_order_items', ['id' => $item->id, 'deleted_at' => null]);
    }

    /** WO-12: Deleting a completed OS must be blocked (422) */
    public function test_wo_cannot_delete_completed_order(): void
    {
        $order = WorkOrder::factory()->completed()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->deleteJson("/api/v1/work-orders/{$order->id}")
            ->assertUnprocessable();
    }

    /** WO-13: Deleting an invoiced OS must be blocked (422) */
    public function test_wo_cannot_delete_invoiced_order(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->deleteJson("/api/v1/work-orders/{$order->id}")
            ->assertUnprocessable();
    }

    /** WO-14: Deleting an open OS must soft-delete it (204) */
    public function test_wo_can_delete_open_order(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->deleteJson("/api/v1/work-orders/{$order->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('work_orders', ['id' => $order->id]);
    }

    /** WO-15: Deleting an OS that has linked AccountReceivable must return 409 */
    public function test_wo_delete_with_ar_dependency_returns_409(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'work_order_id' => $order->id,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->deleteJson("/api/v1/work-orders/{$order->id}")
            ->assertConflict(); // 409
    }

    /** WO-16: Duplicate must create a new OS with the same customer and description */
    public function test_wo_duplicate_creates_copy(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'description' => 'OS Original para duplicar',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson("/api/v1/work-orders/{$order->id}/duplicate")
            ->assertCreated();

        $this->assertDatabaseCount('work_orders', 2);
        $this->assertDatabaseHas('work_orders', [
            'customer_id' => $this->customerA->id,
            'description' => 'OS Original para duplicar',
            'status' => WorkOrder::STATUS_OPEN,
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    /** WO-17: Reopening a cancelled OS must change status back to open */
    public function test_wo_reopen_cancelled_order_succeeds(): void
    {
        $order = WorkOrder::factory()->cancelled()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson("/api/v1/work-orders/{$order->id}/reopen")
            ->assertOk();

        $this->assertDatabaseHas('work_orders', [
            'id' => $order->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }

    /** WO-18: Reopening a non-cancelled OS must be blocked (422) */
    public function test_wo_reopen_non_cancelled_order_fails(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson("/api/v1/work-orders/{$order->id}/reopen")
            ->assertUnprocessable();
    }

    /** WO-19: Metadata endpoint must include statuses and priorities maps */
    public function test_wo_metadata_has_statuses_and_priorities(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson('/api/v1/work-orders-metadata')
            ->assertOk()
            ->assertJsonStructure(['data' => ['statuses', 'priorities']]);
    }

    /** WO-20: Dashboard stats must include all expected aggregation keys */
    public function test_wo_dashboard_stats_returns_correct_structure(): void
    {
        WorkOrder::factory(3)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson('/api/v1/work-orders-dashboard-stats')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'status_counts',
                'avg_completion_hours',
                'month_revenue',
                'sla_compliance',
                'total_orders',
                'top_customers',
            ]]);
    }

    /** WO-21: Cost estimate endpoint must return itemized breakdown */
    public function test_wo_cost_estimate_returns_breakdown(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson("/api/v1/work-orders/{$order->id}/cost-estimate")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'items',
                'items_subtotal',
                'items_discount',
                'displacement_value',
                'global_discount',
                'grand_total',
            ]]);
    }
}
