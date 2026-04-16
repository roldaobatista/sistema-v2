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

class WorkOrderDisplacementControllerTest extends TestCase
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
        Event::fake();

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
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_displacement_snapshot(): void
    {
        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/displacement");

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

    public function test_start_validates_required_coordinates(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/start", []);

        $response->assertStatus(422);
    }

    public function test_start_transitions_work_order_to_in_displacement(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/start", [
            'latitude' => -23.5,
            'longitude' => -46.6,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertNotNull($this->workOrder->fresh()->displacement_started_at);
        $this->assertSame(
            WorkOrder::STATUS_IN_DISPLACEMENT,
            $this->workOrder->fresh()->status
        );
    }

    public function test_start_rejects_duplicate_start(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/start", [
            'latitude' => -23.5,
            'longitude' => -46.6,
        ]);

        $response->assertStatus(422);
    }

    public function test_index_returns_404_for_cross_tenant(): void
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

        $response = $this->getJson("/api/v1/work-orders/{$foreign->id}/displacement");

        $response->assertStatus(404);
    }
}
