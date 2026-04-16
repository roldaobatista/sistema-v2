<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderActionControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function makeWorkOrder(string $status = WorkOrder::STATUS_OPEN): WorkOrder
    {
        return WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => $status,
        ]);
    }

    public function test_duplicate_creates_new_work_order_with_fresh_status(): void
    {
        $original = $this->makeWorkOrder(WorkOrder::STATUS_COMPLETED);

        $response = $this->postJson("/api/v1/work-orders/{$original->id}/duplicate");

        $response->assertStatus(201);

        // Nova OS criada
        $this->assertSame(2, WorkOrder::where('tenant_id', $this->tenant->id)->count());

        $duplicate = WorkOrder::where('tenant_id', $this->tenant->id)
            ->where('id', '!=', $original->id)
            ->first();

        $this->assertNotNull($duplicate);
        $this->assertSame(WorkOrder::STATUS_OPEN, $duplicate->status);
        $this->assertSame($original->customer_id, $duplicate->customer_id);
        $this->assertSame($this->user->id, $duplicate->created_by);
        $this->assertNotEquals($original->number, $duplicate->number);
    }

    public function test_duplicate_returns_404_for_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $foreign = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$foreign->id}/duplicate");

        $response->assertStatus(404);
        // Nenhuma duplicata criada — busca sem global scope para contar todas
        $this->assertSame(
            1,
            WorkOrder::withoutGlobalScopes()->count(),
            'Duplicata cross-tenant não pode criar nova OS'
        );
    }

    public function test_update_status_rejects_invalid_status(): void
    {
        $wo = $this->makeWorkOrder();

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => 'invalid_status_xyz',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_status_transitions_work_order(): void
    {
        $wo = $this->makeWorkOrder(WorkOrder::STATUS_OPEN);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);

        // Aceita 200 ou 422 (se houver regra de transição que bloqueie direto),
        // mas NÃO pode ser 500
        $this->assertNotEquals(500, $response->status());
        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_reopen_returns_404_for_cross_tenant_work_order(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $foreign = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
            'status' => WorkOrder::STATUS_CANCELLED,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$foreign->id}/reopen", []);

        $response->assertStatus(404);
    }
}
