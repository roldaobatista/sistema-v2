<?php

namespace Tests\Feature\Api\V1\Logistics;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoutingOptimizationService;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class RoutingControllerTest extends TestCase
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

    public function test_daily_plan_returns_expected_structure(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/logistics/routing/daily-plan');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'date',
                'technician_id',
                'optimized_path',
            ]]);
    }

    public function test_daily_plan_accepts_date_parameter(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/logistics/routing/daily-plan?date=2026-06-01');

        $response->assertOk();
        $this->assertEquals('2026-06-01', $response->json('data.date'));
    }

    public function test_daily_plan_defaults_to_today(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/logistics/routing/daily-plan');

        $response->assertOk();
        $this->assertEquals(now()->toDateString(), $response->json('data.date'));
    }

    public function test_daily_plan_associates_with_authenticated_technician(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/logistics/routing/daily-plan');

        $response->assertOk();
        $this->assertEquals($this->user->id, $response->json('data.technician_id'));
    }

    public function test_daily_plan_calls_routing_service_with_correct_params(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->mock(RoutingOptimizationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('optimizeDailyPlan')
                ->once()
                ->with($this->tenant->id, $this->user->id, '2026-07-01')
                ->andReturn(['path' => 'mocked']);
        });

        $response = $this->getJson('/api/v1/logistics/routing/daily-plan?date=2026-07-01');

        $response->assertOk();
        $this->assertEquals(['path' => 'mocked'], $response->json('data.optimized_path'));
    }

    public function test_daily_plan_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/logistics/routing/daily-plan');
        $response->assertUnauthorized();
    }

    public function test_daily_plan_respects_permissions(): void
    {
        Gate::before(fn () => false); // Deny all
        Sanctum::actingAs($this->user, ['*']);
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->getJson('/api/v1/logistics/routing/daily-plan');

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_daily_plan_handles_service_exceptions(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->mock(RoutingOptimizationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('optimizeDailyPlan')
                ->andThrow(new \Exception('Service unavailable'));
        });

        $response = $this->getJson('/api/v1/logistics/routing/daily-plan');

        $response->assertStatus(500); // Because Controller doesn't catch it currently, it bubbles up (or Laravel handles as 500)
    }
}
