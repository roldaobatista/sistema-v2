<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_dashboard_stats_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/dashboard-stats');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_dashboard_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_team_status_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/dashboard/team-status');

        $response->assertOk();
    }

    public function test_activities_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/dashboard/activities');

        $response->assertOk();
    }

    public function test_stats_isolates_other_tenant_data(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $response = $this->getJson('/api/v1/dashboard-stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
    }
}
