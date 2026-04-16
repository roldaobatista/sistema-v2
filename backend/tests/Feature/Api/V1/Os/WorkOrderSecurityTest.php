<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderSecurityTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $user;

    private User $otherUser;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->otherUser = User::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'current_tenant_id' => $this->otherTenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);
    }

    // ── CROSS-TENANT ISOLATION ──

    public function test_user_cannot_view_work_order_from_other_tenant(): void
    {
        Sanctum::actingAs($this->otherUser, ['*']);
        app()->instance('current_tenant_id', $this->otherTenant->id);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}");

        $response->assertStatus(404)
            ->assertDontSee($this->workOrder->description ?? '');
    }

    public function test_user_cannot_update_work_order_from_other_tenant(): void
    {
        Sanctum::actingAs($this->otherUser, ['*']);
        app()->instance('current_tenant_id', $this->otherTenant->id);

        $response = $this->putJson("/api/v1/work-orders/{$this->workOrder->id}", [
            'description' => 'Hacked',
        ]);

        $response->assertStatus(404);
    }

    public function test_user_cannot_delete_work_order_from_other_tenant(): void
    {
        Sanctum::actingAs($this->otherUser, ['*']);
        app()->instance('current_tenant_id', $this->otherTenant->id);

        $response = $this->deleteJson("/api/v1/work-orders/{$this->workOrder->id}");

        $response->assertStatus(404);
    }

    public function test_user_cannot_change_status_of_other_tenant_work_order(): void
    {
        Sanctum::actingAs($this->otherUser, ['*']);
        app()->instance('current_tenant_id', $this->otherTenant->id);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/status", [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(404);
    }

    // ── PERMISSION REQUIRED ──

    public function test_unauthenticated_user_cannot_access_work_orders(): void
    {
        $response = $this->getJson('/api/v1/work-orders');

        $response->assertStatus(401);
    }

    public function test_index_returns_only_own_tenant_work_orders(): void
    {
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        // Create OS for other tenant
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->otherUser->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/work-orders');

        $response->assertOk();
        $ids = collect($response->json('data.data') ?? $response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->workOrder->id, $ids);
        $this->assertNotContains($otherWo->id, $ids);
    }
}
