<?php

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RbacPermissionsTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $manager;

    private User $tech;

    private User $viewer;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = $this->createUserWithRole('admin');
        $this->manager = $this->createUserWithRole('gerente');
        $this->tech = $this->createUserWithRole('tecnico');
        $this->viewer = $this->createUserWithRole('visualizador');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $user->assignRole($role);

        return $user;
    }

    // ── Admin ──

    public function test_admin_can_list_work_orders(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders');
        $response->assertOk();
    }

    public function test_admin_can_create_work_order(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Admin WO',
            'description' => 'Test description',
        ]);
        $response->assertCreated();
    }

    public function test_admin_can_delete_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/work-orders/{$wo->id}");
        $response->assertNoContent();
    }

    // ── Manager ──

    public function test_manager_can_list_work_orders(): void
    {
        $response = $this->actingAs($this->manager)->getJson('/api/v1/work-orders');
        $response->assertOk();
    }

    public function test_manager_can_create_work_order(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Manager WO',
            'description' => 'Test description',
        ]);
        $response->assertCreated();
    }

    // ── Technician ──

    public function test_technician_can_list_work_orders(): void
    {
        $response = $this->actingAs($this->tech)->getJson('/api/v1/work-orders');
        $response->assertOk();
    }

    public function test_technician_can_view_assigned_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->tech->id,
        ]);

        $response = $this->actingAs($this->tech)->getJson("/api/v1/work-orders/{$wo->id}");
        $response->assertOk();
    }

    // ── Viewer ──

    public function test_viewer_can_list_work_orders(): void
    {
        $response = $this->actingAs($this->viewer)->getJson('/api/v1/work-orders');
        $response->assertOk();
    }

    public function test_viewer_cannot_create_work_order(): void
    {
        $response = $this->actingAs($this->viewer)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Viewer WO',
            'description' => 'Test description',
        ]);
        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->viewer)->deleteJson("/api/v1/work-orders/{$wo->id}");
        $response->assertForbidden();
    }

    // ── Customer RBAC ──

    public function test_admin_can_manage_customers(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/customers', [
            'name' => 'New Customer',
            'type' => 'PJ',
        ]);
        $response->assertCreated();
    }

    public function test_viewer_cannot_create_customer(): void
    {
        $response = $this->actingAs($this->viewer)->postJson('/api/v1/customers', [
            'name' => 'No access',
        ]);
        $response->assertForbidden();
    }

    // ── Equipment RBAC ──

    public function test_admin_can_create_equipment(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'name' => 'Balança teste',
            'type' => 'balança',
        ]);

        $response->assertCreated();
    }

    public function test_viewer_cannot_delete_equipment(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->viewer)->deleteJson("/api/v1/equipments/{$eq->id}");
        $response->assertForbidden();
    }
}
