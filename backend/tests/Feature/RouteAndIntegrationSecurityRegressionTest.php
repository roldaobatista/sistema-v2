<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RouteAndIntegrationSecurityRegressionTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_google_calendar_routes_have_granular_permission_middleware(): void
    {
        $statusRoute = $this->findRoute('GET', 'api/v1/integrations/google-calendar/status');
        $authUrlRoute = $this->findRoute('GET', 'api/v1/integrations/google-calendar/auth-url');
        $callbackRoute = $this->findRoute('POST', 'api/v1/integrations/google-calendar/callback');
        $disconnectRoute = $this->findRoute('POST', 'api/v1/integrations/google-calendar/disconnect');
        $syncRoute = $this->findRoute('POST', 'api/v1/integrations/google-calendar/sync');

        $this->assertContains('check.permission:platform.settings.view', $statusRoute->gatherMiddleware());
        $this->assertContains('check.permission:platform.settings.view', $authUrlRoute->gatherMiddleware());
        $this->assertContains('check.permission:platform.settings.manage', $callbackRoute->gatherMiddleware());
        $this->assertContains('check.permission:platform.settings.manage', $disconnectRoute->gatherMiddleware());
        $this->assertContains('check.permission:platform.settings.manage', $syncRoute->gatherMiddleware());
    }

    public function test_google_calendar_callback_requires_code_via_form_request(): void
    {
        Permission::findOrCreate('platform.settings.manage', 'web');
        $this->user->syncPermissions(['platform.settings.manage']);

        $this->postJson('/api/v1/integrations/google-calendar/callback', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_route_plan_store_requires_manage_permission(): void
    {
        $route = $this->findRoute('POST', 'api/v1/advanced/route-plans');

        $this->assertContains('check.permission:route.plan.manage', $route->gatherMiddleware());
    }

    public function test_work_order_execution_and_displacement_mutations_require_change_status_permission(): void
    {
        $routes = [
            ['POST', 'api/v1/work-orders/{work_order}/displacement/start'],
            ['POST', 'api/v1/work-orders/{work_order}/displacement/arrive'],
            ['POST', 'api/v1/work-orders/{work_order}/displacement/location'],
            ['POST', 'api/v1/work-orders/{work_order}/displacement/stops'],
            ['PATCH', 'api/v1/work-orders/{work_order}/displacement/stops/{stop}'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/start-displacement'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/pause-displacement'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/resume-displacement'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/arrive'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/start-service'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/pause-service'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/resume-service'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/finalize'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/start-return'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/pause-return'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/resume-return'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/arrive-return'],
            ['POST', 'api/v1/work-orders/{work_order}/execution/close-without-return'],
        ];

        foreach ($routes as [$method, $uri]) {
            $route = $this->findRoute($method, $uri);

            $this->assertContains(
                'check.permission:os.work_order.change_status',
                $route->gatherMiddleware(),
                "Middleware incorreto em {$method} {$uri}"
            );
        }
    }

    private function findRoute(string $method, string $uri): LaravelRoute
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (LaravelRoute $candidate): bool => $candidate->uri() === $uri && in_array($method, $candidate->methods(), true)
        );

        $this->assertNotNull($route, "Rota nao encontrada: {$method} {$uri}");

        return $route;
    }
}
