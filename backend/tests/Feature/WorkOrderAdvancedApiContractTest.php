<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderAdvancedApiContractTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_execution_start_displacement_returns_data_envelope_with_message(): void
    {
        $workOrder = $this->createTechnicianWorkOrder($this->tenant->id);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/execution/start-displacement", []);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['status', 'displacement_started_at'],
                'message',
            ])
            ->assertJsonPath('data.status', WorkOrder::STATUS_IN_DISPLACEMENT)
            ->assertJsonPath('message', 'Deslocamento iniciado.');
    }

    public function test_displacement_index_returns_data_envelope(): void
    {
        $workOrder = $this->createTechnicianWorkOrder($this->tenant->id);

        $response = $this->getJson("/api/v1/work-orders/{$workOrder->id}/displacement");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'displacement_started_at',
                    'displacement_arrived_at',
                    'displacement_duration_minutes',
                    'displacement_status',
                    'stops',
                    'locations_count',
                ],
            ]);
    }

    public function test_approval_request_returns_data_envelope_and_sets_waiting_status(): void
    {
        $workOrder = $this->createTechnicianWorkOrder($this->tenant->id);
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/approvals/request", [
            'approver_ids' => [$approver->id],
            'notes' => 'Necessita aprovação técnica',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['approval_ids'],
                'message',
            ])
            ->assertJsonCount(1, 'data.approval_ids')
            ->assertJsonPath('message', 'Aprovação solicitada');

        $this->assertDatabaseHas('work_order_approvals', [
            'work_order_id' => $workOrder->id,
            'approver_id' => $approver->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'status' => WorkOrder::STATUS_WAITING_APPROVAL,
        ]);
    }

    public function test_approval_respond_invalid_action_returns_422_message(): void
    {
        $workOrder = $this->createTechnicianWorkOrder($this->tenant->id);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/approvals/{$this->user->id}/invalid", []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', 'Ação inválida');
    }

    public function test_execution_cross_tenant_is_blocked_with_predictable_403(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignWorkOrder = $this->createTechnicianWorkOrder($otherTenant->id);

        app()->forgetInstance('current_tenant_id');

        $response = $this->postJson("/api/v1/work-orders/{$foreignWorkOrder->id}/execution/start-displacement", []);

        $response->assertStatus(403)
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', 'Acesso negado: OS não pertence ao tenant atual.');
    }

    private function createTechnicianWorkOrder(int $tenantId): WorkOrder
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $tenantId,
        ]);

        return WorkOrder::factory()->create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }
}
