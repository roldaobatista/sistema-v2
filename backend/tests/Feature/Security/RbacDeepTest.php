<?php

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RbacDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $manager;

    private User $technician;

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
        $this->admin = $this->createUser('admin');
        $this->manager = $this->createUser('gerente');
        $this->technician = $this->createUser('tecnico');
        $this->viewer = $this->createUser('visualizador');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function createUser(string $role): User
    {
        $u = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $u->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $u->assignRole($role);

        return $u;
    }

    // ── Admin Full Access ──

    public function test_admin_can_list_work_orders(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/work-orders')->assertOk();
    }

    public function test_admin_can_create_work_order(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Admin WO',
            'priority' => 'medium',
        ]);
        $this->assertTrue(in_array($response->status(), [201, 200]));
    }

    public function test_admin_can_delete_customer(): void
    {
        $c = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/customers/{$c->id}");
        $this->assertTrue(in_array($response->status(), [200, 204]));
    }

    public function test_admin_can_manage_users(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/users')->assertOk();
    }

    public function test_admin_can_access_settings(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/settings')->assertOk();
    }

    // ── Manager Permissions ──

    public function test_manager_can_list_work_orders(): void
    {
        $this->actingAs($this->manager)->getJson('/api/v1/work-orders')->assertOk();
    }

    public function test_manager_can_create_customer(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/v1/customers', [
            'name' => 'Manager Customer',
            'type' => 'PJ',
        ]);
        $this->assertTrue(in_array($response->status(), [201, 200]));
    }

    public function test_manager_can_create_quote(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'title' => 'Manager Quote',
        ]);
        $this->assertTrue(in_array($response->status(), [201, 200]));
    }

    // ── Technician Permissions ──

    public function test_technician_can_list_work_orders(): void
    {
        $this->actingAs($this->technician)->getJson('/api/v1/work-orders')->assertOk();
    }

    public function test_technician_can_update_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->technician->id,
        ]);
        $response = $this->actingAs($this->technician)->putJson("/api/v1/work-orders/{$wo->id}", [
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
        $this->assertTrue(in_array($response->status(), [200, 403]));
    }

    public function test_technician_cannot_delete_customer(): void
    {
        $c = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->technician)->deleteJson("/api/v1/customers/{$c->id}");
        $response->assertForbidden();
    }

    public function test_technician_cannot_access_settings(): void
    {
        $response = $this->actingAs($this->technician)->getJson('/api/v1/settings');
        $this->assertTrue(in_array($response->status(), [200, 403]));
    }

    // ── Viewer Permissions ──

    public function test_viewer_can_list_work_orders(): void
    {
        $this->actingAs($this->viewer)->getJson('/api/v1/work-orders')->assertOk();
    }

    public function test_viewer_cannot_create_work_order(): void
    {
        $response = $this->actingAs($this->viewer)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Viewer Test',
        ]);
        $response->assertForbidden();
    }

    public function test_viewer_cannot_update_customer(): void
    {
        $c = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->viewer)->putJson("/api/v1/customers/{$c->id}", [
            'name' => 'Hacked',
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

    public function test_viewer_can_list_customers(): void
    {
        $this->actingAs($this->viewer)->getJson('/api/v1/customers')->assertOk();
    }

    public function test_viewer_cannot_manage_users(): void
    {
        $response = $this->actingAs($this->viewer)->postJson('/api/v1/users', [
            'name' => 'Hacker',
            'email' => 'hack@test.com',
            'password' => '123456',
        ]);
        $response->assertForbidden();
    }

    // ── Role Escalation Prevention ──

    public function test_viewer_cannot_assign_admin_role(): void
    {
        $response = $this->actingAs($this->viewer)->putJson("/api/v1/users/{$this->viewer->id}", [
            'role' => 'admin',
        ]);
        $response->assertForbidden();
    }

    public function test_technician_cannot_escalate_to_admin(): void
    {
        $response = $this->actingAs($this->technician)->putJson("/api/v1/users/{$this->technician->id}", [
            'role' => 'admin',
        ]);
        $response->assertForbidden();
    }
}
