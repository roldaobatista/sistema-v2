<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetFeatureTest extends TestCase
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
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_fleet_dashboard_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/fleet/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'total_vehicles',
                'active',
                'pending_fines',
            ],
        ]);
    }

    public function test_fleet_vehicles_list_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/fleet/vehicles');

        $response->assertOk();
    }
}
