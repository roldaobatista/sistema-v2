<?php

namespace Tests\Unit\Policies;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\WorkOrderPolicy;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkOrderPolicyTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $tech;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();

        // Create required permissions
        $permissions = [
            'os.work_order.view',
            'os.work_order.create',
            'os.work_order.update',
            'os.work_order.delete',
            'os.work_order.change_status',
        ];
        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Set tenant context before assigning roles/permissions
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        // Get roles (already seeded by TestCase) and assign permissions
        $adminRole = Role::findByName('admin', 'web');
        $adminRole->givePermissionTo($permissions);

        $tecnicoRole = Role::findByName('tecnico', 'web');
        $tecnicoRole->givePermissionTo([
            'os.work_order.view',
            'os.work_order.update',
            'os.work_order.change_status',
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->admin->assignRole('admin');

        $this->tech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->tech->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->tech->assignRole('tecnico');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    public function test_admin_can_view_any_work_orders(): void
    {
        $this->actingAs($this->admin);
        $policy = new WorkOrderPolicy;

        $this->assertTrue($policy->viewAny($this->admin));
    }

    public function test_admin_can_create_work_order(): void
    {
        $this->actingAs($this->admin);
        $policy = new WorkOrderPolicy;

        $this->assertTrue($policy->create($this->admin));
    }

    public function test_admin_can_view_work_order(): void
    {
        $this->actingAs($this->admin);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $policy = new WorkOrderPolicy;
        $this->assertTrue($policy->view($this->admin, $wo));
    }

    public function test_admin_can_update_work_order(): void
    {
        $this->actingAs($this->admin);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $policy = new WorkOrderPolicy;
        $this->assertTrue($policy->update($this->admin, $wo));
    }

    public function test_admin_can_delete_work_order(): void
    {
        $this->actingAs($this->admin);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $policy = new WorkOrderPolicy;
        $this->assertTrue($policy->delete($this->admin, $wo));
    }

    public function test_technician_can_view_assigned_work_order(): void
    {
        $this->actingAs($this->tech);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->tech->id,
        ]);

        $policy = new WorkOrderPolicy;
        $result = $policy->view($this->tech, $wo);
        $this->assertTrue($result);
    }

    public function test_user_cannot_access_other_tenant_work_order(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        app()->instance('current_tenant_id', $otherTenant->id);
        $this->actingAs($otherUser);
        $policy = new WorkOrderPolicy;

        $result = $policy->view($otherUser, $wo);
        $this->assertFalse($result);
    }
}
