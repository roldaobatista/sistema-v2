<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Observability;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Observability\ObservabilityDashboardService;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ObservabilityDashboardControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->setTenantContext($this->tenant->id);
    }

    public function test_can_view_observability_dashboard_with_permission(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        Permission::findOrCreate('platform.settings.view', 'web');
        $this->user->givePermissionTo('platform.settings.view');

        $service = \Mockery::mock(ObservabilityDashboardService::class);
        $service->shouldReceive('build')->once()->andReturn([
            'summary' => ['status' => 'healthy', 'active_alerts' => 1],
            'health' => ['status' => 'healthy', 'checks' => ['mysql' => ['ok' => true]]],
            'metrics' => [['path' => '/api/health', 'count' => 3, 'p95_ms' => 20]],
            'alerts' => [['level' => 'warning', 'message' => 'Queue rising']],
            'history' => [],
            'links' => ['horizon' => '/horizon', 'pulse' => '/pulse'],
        ]);
        $this->app->instance(ObservabilityDashboardService::class, $service);

        $this->getJson('/api/v1/observability/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['status', 'active_alerts'],
                    'health',
                    'metrics',
                    'alerts',
                    'history',
                    'links',
                ],
            ]);
    }

    public function test_dashboard_requires_permission(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        Gate::before(fn () => null);

        $this->getJson('/api/v1/observability/dashboard')
            ->assertForbidden();
    }

    public function test_dashboard_requires_authentication(): void
    {
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/observability/dashboard')
            ->assertUnauthorized();
    }
}
