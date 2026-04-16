<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Commission Dashboard Tests — validates overview KPIs, ranking,
 * evolution time series, distribution by rule, and distribution by role.
 */
class CommissionDashboardTest extends TestCase
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

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_commission_dashboard_overview(): void
    {
        $response = $this->getJson('/api/v1/commission-dashboard/overview');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('pending', $data);
        $this->assertArrayHasKey('approved', $data);
        $this->assertArrayHasKey('paid_this_month', $data);
    }

    public function test_commission_dashboard_ranking(): void
    {
        $response = $this->getJson('/api/v1/commission-dashboard/ranking');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    public function test_commission_dashboard_evolution(): void
    {
        $response = $this->getJson('/api/v1/commission-dashboard/evolution?months=6');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    public function test_commission_dashboard_by_rule(): void
    {
        $response = $this->getJson('/api/v1/commission-dashboard/by-rule');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_commission_dashboard_by_role(): void
    {
        $response = $this->getJson('/api/v1/commission-dashboard/by-role');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
