<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkOrderPermissionAlignmentTest extends TestCase
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
            'is_active' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        setPermissionsTeamId($this->tenant->id);

        foreach ([
            'os.work_order.view',
            'os.work_order.update',
            'os.work_order.change_status',
        ] as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $this->user->givePermissionTo([
            'os.work_order.view',
            'os.work_order.update',
            'os.work_order.change_status',
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_user_with_only_change_status_permission_can_update_status(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $this->postJson("/api/v1/work-orders/{$workOrder->id}/status", [
            'status' => WorkOrder::STATUS_AWAITING_DISPATCH,
        ])->assertOk()
            ->assertJsonPath('data.status', WorkOrder::STATUS_AWAITING_DISPATCH);
    }

    public function test_user_with_only_change_status_permission_can_reopen_cancelled_work_order(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Teste',
        ]);

        $this->postJson("/api/v1/work-orders/{$workOrder->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', WorkOrder::STATUS_OPEN);
    }

    public function test_user_with_only_change_status_permission_can_register_checkin(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $this->postJson("/api/v1/work-orders/{$workOrder->id}/checkin", [
            'lat' => -15.601,
            'lng' => -56.097,
        ])->assertOk()
            ->assertJsonPath('message', 'Check-in registrado.');
    }

    public function test_scoped_technician_list_includes_pivot_assignment_and_excludes_unlinked_work_orders(): void
    {
        $this->user->assignRole(Role::firstOrCreate([
            'name' => Role::TECNICO,
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]));
        $otherCreator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $linkedWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => null,
        ]);
        $linkedWorkOrder->technicians()->attach($this->user->id, ['role' => Role::TECNICO, 'tenant_id' => $linkedWorkOrder->tenant_id]);

        $unlinkedWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $otherCreator->id,
            'assigned_to' => null,
        ]);

        $this->getJson('/api/v1/work-orders')
            ->assertOk()
            ->assertJsonFragment(['id' => $linkedWorkOrder->id])
            ->assertJsonMissing(['id' => $unlinkedWorkOrder->id]);
    }

    public function test_scoped_technician_cannot_open_unlinked_work_order(): void
    {
        $this->user->assignRole(Role::firstOrCreate([
            'name' => Role::TECNICO,
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]));
        $otherCreator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $otherCreator->id,
            'assigned_to' => null,
        ]);

        $this->getJson("/api/v1/work-orders/{$workOrder->id}")
            ->assertStatus(403);
    }
}
