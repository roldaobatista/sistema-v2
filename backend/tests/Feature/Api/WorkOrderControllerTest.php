<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class WorkOrderControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    public function test_index_returns_paginated_work_orders(): void
    {
        WorkOrder::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/work-orders');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_store_creates_new_work_order(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/work-orders', [
                'customer_id' => $this->customer->id,
                'title' => 'Calibração Balança 100kg',
                'priority' => 'normal',
                'description' => 'Calibração preventiva',
            ]);

        $response->assertCreated();
    }

    public function test_show_returns_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/work-orders/{$wo->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $wo->id]);
    }

    public function test_update_modifies_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/work-orders/{$wo->id}", [
                'title' => 'Título atualizado',
                'priority' => 'high',
            ]);

        $response->assertOk();
    }

    public function test_destroy_soft_deletes_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/work-orders/{$wo->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('work_orders', ['id' => $wo->id]);
    }

    public function test_store_validation_fails_without_customer(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/work-orders', [
                'title' => 'Sem customer',
            ]);

        $response->assertUnprocessable();
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/work-orders/99999');

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/v1/work-orders');
        $response->assertUnauthorized();
    }

    public function test_index_filters_by_status(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/work-orders?status=open');

        $response->assertOk();
    }

    public function test_index_filters_by_customer(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/work-orders?customer_id={$this->customer->id}");

        $response->assertOk();
    }

    public function test_index_search_by_os_number(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/work-orders?search={$wo->os_number}");

        $response->assertOk();
    }

    public function test_cross_tenant_returns_empty(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);
        $otherUser->assignRole('admin');

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        app()->instance('current_tenant_id', $otherTenant->id);
        $response = $this->actingAs($otherUser)
            ->getJson('/api/v1/work-orders');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }
}
