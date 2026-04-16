<?php

namespace Tests\Feature\Api\V1\Logistics;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\RoutePlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutePlanControllerTest extends TestCase
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

    public function test_can_list_route_plans(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        RoutePlan::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->user->id,
            'plan_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/logistics/route-plans');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'technician_id', 'plan_date']]]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_route_plans_list_filters_by_technician(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $tech2 = User::factory()->create(['tenant_id' => $this->tenant->id]);

        RoutePlan::factory()->create(['tenant_id' => $this->tenant->id, 'technician_id' => $this->user->id]);
        RoutePlan::factory()->create(['tenant_id' => $this->tenant->id, 'technician_id' => $tech2->id]);

        $response = $this->getJson("/api/v1/logistics/route-plans?technician_id={$this->user->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->user->id, $response->json('data.0.technician_id'));
    }

    public function test_route_plans_list_filters_by_date(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        RoutePlan::factory()->create(['tenant_id' => $this->tenant->id, 'plan_date' => '2026-01-10']);
        RoutePlan::factory()->create(['tenant_id' => $this->tenant->id, 'plan_date' => '2026-02-15']);

        $response = $this->getJson('/api/v1/logistics/route-plans?date=2026-01-10');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('2026-01-10', $response->json('data.0.plan_date'));
    }

    public function test_can_store_route_plan(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $payload = [
            'technician_id' => $this->user->id,
            'plan_date' => '2026-05-10',
            'status' => 'planned',
            'stops' => [
                ['work_order_id' => 1, 'order' => 1],
            ],
        ];

        $response = $this->postJson('/api/v1/logistics/route-plans', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('route_plans', [
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->user->id,
            'plan_date' => '2026-05-10',
        ]);
    }

    public function test_store_route_plan_validates_payload(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/logistics/route-plans', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['technician_id', 'plan_date']);
    }

    public function test_route_plans_tenant_isolation(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $otherTenant = Tenant::factory()->create();
        RoutePlan::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson('/api/v1/logistics/route-plans');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/logistics/route-plans');
        $response->assertUnauthorized();
    }

    public function test_respects_permissions_403(): void
    {
        Gate::before(fn () => false); // Deny all
        Sanctum::actingAs($this->user, ['*']);
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->getJson('/api/v1/logistics/route-plans');
        $this->assertContains($response->status(), [403, 404]);
    }
}
