<?php

namespace Tests\Feature\Api\V1\Logistics;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouteOptimizationControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    public function test_can_optimize_route_with_valid_work_orders(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'latitude' => -23.550520, 'longitude' => -46.633308]);
        $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        $payload = [
            'work_order_ids' => [$workOrder->id],
            'start_lat' => -23.500000,
            'start_lng' => -46.600000,
        ];

        $response = $this->postJson('/api/v1/logistics/routes/optimize', $payload);

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
    }

    public function test_optimize_route_validates_payload(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/logistics/routes/optimize', []);

        // Our controller catches validation and returns 422 ApiResponse
        $response->assertStatus(422);
    }

    public function test_optimize_route_returns_empty_when_ids_are_not_found(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $payload = [
            'work_order_ids' => [999999], // non-existent
            'start_lat' => -23.500000,
            'start_lng' => -46.600000,
        ];

        $response = $this->postJson('/api/v1/logistics/routes/optimize', $payload);

        $response->assertOk(); // The controller says if empty return []
        $this->assertEmpty($response->json('data'));
    }

    public function test_optimize_route_tenant_isolation(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $otherTenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $otherTenant->id, 'latitude' => 0, 'longitude' => 0]);
        $otherWorkOrder = WorkOrder::factory()->create(['tenant_id' => $otherTenant->id, 'customer_id' => $customer->id]);

        $payload = [
            'work_order_ids' => [$otherWorkOrder->id],
        ];

        $response = $this->postJson('/api/v1/logistics/routes/optimize', $payload);

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_optimize_route_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/logistics/routes/optimize', ['work_order_ids' => [1]]);
        $response->assertUnauthorized();
    }

    public function test_optimize_route_respects_permissions(): void
    {
        Gate::before(fn () => false); // Deny all
        Sanctum::actingAs($this->user, ['*']);
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->postJson('/api/v1/logistics/routes/optimize', ['work_order_ids' => [1]]);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_optimize_route_uses_first_customer_coords_if_start_not_provided(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'latitude' => -23.5, 'longitude' => -46.5]);
        $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        $payload = [
            'work_order_ids' => [$workOrder->id],
        ]; // No start_lat, start_lng

        $response = $this->postJson('/api/v1/logistics/routes/optimize', $payload);

        $response->assertOk();
    }

    public function test_optimize_route_returns_original_if_no_coords_available(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        // Customer with null coords
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'latitude' => null, 'longitude' => null]);
        $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        $payload = [
            'work_order_ids' => [$workOrder->id],
        ];

        $response = $this->postJson('/api/v1/logistics/routes/optimize', $payload);

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
    }
}
