<?php

namespace Tests\Feature\Api\V1\Operational;

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
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createCustomerAt(float $lat, float $lng): Customer
    {
        return Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    private function createWorkOrderForCustomer(Customer $customer): WorkOrder
    {
        return WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
    }

    // ─── OPTIMIZE ─────────────────────────────────────────────────────

    public function test_optimize_returns_sorted_work_orders(): void
    {
        // Start at (0,0). C1 far (10,10), C2 near (0.1,0.1), C3 medium (2,2)
        $c1 = $this->createCustomerAt(10.0, 10.0);
        $c2 = $this->createCustomerAt(0.1, 0.1);
        $c3 = $this->createCustomerAt(2.0, 2.0);

        $wo1 = $this->createWorkOrderForCustomer($c1);
        $wo2 = $this->createWorkOrderForCustomer($c2);
        $wo3 = $this->createWorkOrderForCustomer($c3);

        $response = $this->postJson('/api/v1/operational/route-optimization', [
            'work_order_ids' => [$wo1->id, $wo2->id, $wo3->id],
            'start_lat' => 0.0,
            'start_lng' => 0.0,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);

        // Nearest neighbor: C2 (0.1,0.1) -> C3 (2,2) -> C1 (10,10)
        $this->assertEquals($wo2->id, $data[0]['id']);
        $this->assertEquals($wo3->id, $data[1]['id']);
        $this->assertEquals($wo1->id, $data[2]['id']);
    }

    public function test_optimize_with_single_work_order(): void
    {
        $customer = $this->createCustomerAt(5.0, 5.0);
        $wo = $this->createWorkOrderForCustomer($customer);

        $response = $this->postJson('/api/v1/operational/route-optimization', [
            'work_order_ids' => [$wo->id],
            'start_lat' => 0.0,
            'start_lng' => 0.0,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($wo->id, $data[0]['id']);
    }

    public function test_optimize_returns_empty_for_no_matching_work_orders(): void
    {
        $response = $this->postJson('/api/v1/operational/route-optimization', [
            'work_order_ids' => [999998, 999999],
            'start_lat' => 0.0,
            'start_lng' => 0.0,
        ]);

        // Non-existent IDs should fail validation (exists rule)
        $response->assertUnprocessable();
    }

    public function test_optimize_uses_customer_coordinates_as_fallback_start(): void
    {
        // When start_lat/start_lng not provided, uses first WO's customer
        $c1 = $this->createCustomerAt(1.0, 1.0);
        $c2 = $this->createCustomerAt(2.0, 2.0);
        $wo1 = $this->createWorkOrderForCustomer($c1);
        $wo2 = $this->createWorkOrderForCustomer($c2);

        $response = $this->postJson('/api/v1/operational/route-optimization', [
            'work_order_ids' => [$wo1->id, $wo2->id],
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_optimize_handles_customers_without_coordinates(): void
    {
        $c1 = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'latitude' => null,
            'longitude' => null,
        ]);
        $c2 = $this->createCustomerAt(1.0, 1.0);

        $wo1 = $this->createWorkOrderForCustomer($c1);
        $wo2 = $this->createWorkOrderForCustomer($c2);

        $response = $this->postJson('/api/v1/operational/route-optimization', [
            'work_order_ids' => [$wo1->id, $wo2->id],
            'start_lat' => 0.0,
            'start_lng' => 0.0,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        // Both should be returned, the one without coords appended at end
        $this->assertCount(2, $data);
    }

    // ─── VALIDATION ───────────────────────────────────────────────────

    public function test_validation_requires_work_order_ids(): void
    {
        $response = $this->postJson('/api/v1/operational/route-optimization', [
            'start_lat' => 0.0,
            'start_lng' => 0.0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['work_order_ids']);
    }

    public function test_validation_work_order_ids_must_be_array(): void
    {
        $response = $this->postJson('/api/v1/operational/route-optimization', [
            'work_order_ids' => 'not_an_array',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['work_order_ids']);
    }

    public function test_validation_start_lat_must_be_numeric(): void
    {
        $customer = $this->createCustomerAt(1.0, 1.0);
        $wo = $this->createWorkOrderForCustomer($customer);

        $response = $this->postJson('/api/v1/operational/route-optimization', [
            'work_order_ids' => [$wo->id],
            'start_lat' => 'not_a_number',
            'start_lng' => 0.0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_lat']);
    }

    // ─── NEAREST NEIGHBOR CORRECTNESS ─────────────────────────────────

    public function test_nearest_neighbor_chain(): void
    {
        // Layout: start(0,0) -> A(0,1) -> B(0,2) -> C(0,10)
        // Nearest to (0,0) is A, then nearest to A is B, then C
        $cA = $this->createCustomerAt(0.0, 1.0);
        $cB = $this->createCustomerAt(0.0, 2.0);
        $cC = $this->createCustomerAt(0.0, 10.0);

        $woA = $this->createWorkOrderForCustomer($cA);
        $woB = $this->createWorkOrderForCustomer($cB);
        $woC = $this->createWorkOrderForCustomer($cC);

        $response = $this->postJson('/api/v1/operational/route-optimization', [
            'work_order_ids' => [$woC->id, $woA->id, $woB->id], // send out of order
            'start_lat' => 0.0,
            'start_lng' => 0.0,
        ]);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertEquals([$woA->id, $woB->id, $woC->id], $ids);
    }

    // ─── AUTH ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_gets_401(): void
    {
        Sanctum::actingAs(new User, []);
        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/v1/operational/route-optimization', [
            'work_order_ids' => [1],
        ]);

        $response->assertUnauthorized();
    }
}
