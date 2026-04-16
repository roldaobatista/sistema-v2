<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\FiscalNote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderIntegrationControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_satisfaction_returns_200(): void
    {
        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/satisfaction");

        $response->assertOk();
    }

    public function test_cost_estimate_returns_200(): void
    {
        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/cost-estimate");

        $response->assertOk();
    }

    public function test_fiscal_notes_returns_200(): void
    {
        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/fiscal-notes");

        $response->assertOk();
    }

    public function test_fiscal_notes_uses_canonical_paginated_envelope(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        FiscalNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/fiscal-notes");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'work_order_id', 'tenant_id'],
                ],
                'meta' => ['current_page', 'per_page'],
            ]);
    }

    public function test_audit_trail_returns_200(): void
    {
        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/audit-trail");

        $response->assertOk();
    }

    public function test_audit_trail_returns_404_for_cross_tenant_work_order(): void
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

        $response = $this->getJson("/api/v1/work-orders/{$foreign->id}/audit-trail");

        $response->assertStatus(404);
    }

    public function test_satisfaction_returns_404_for_cross_tenant(): void
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

        $response = $this->getJson("/api/v1/work-orders/{$foreign->id}/satisfaction");

        $response->assertStatus(404);
    }
}
