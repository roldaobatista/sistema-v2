<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SlaDashboardControllerTest extends TestCase
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

    public function test_overview_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/sla-dashboard/overview');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_breached_returns_list(): void
    {
        $response = $this->getJson('/api/v1/sla-dashboard/breached');

        $response->assertOk();
    }

    public function test_by_policy_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/sla-dashboard/by-policy');

        $response->assertOk();
    }

    public function test_by_technician_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/sla-dashboard/by-technician');

        $response->assertOk();
    }

    public function test_trends_returns_time_series(): void
    {
        $response = $this->getJson('/api/v1/sla-dashboard/trends');

        $response->assertOk();
    }
}
