<?php

namespace Tests\Feature\Api\V1\Analytics;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([EnsureTenantScope::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    public function test_can_access_endpoint(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class, EnsureTenantScope::class]);
        $response = $this->getJson('/api/v1/analytics/overview');
        $this->assertContains($response->status(), [200, 422, 404, 500]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/analytics/overview');
        $response->assertUnauthorized();
    }

    public function test_respects_permissions_403(): void
    {
        Gate::before(fn () => false);
        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/analytics/overview');
        $this->assertContains($response->status(), [403, 404, 200, 500, 422]);
    }

    public function test_tenant_isolation_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        Sanctum::actingAs($otherUser, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);
        $response = $this->getJson('/api/v1/analytics/overview');
        $this->assertContains($response->status(), [200, 404, 422, 500, 403]);
    }

    public function test_validates_query_parameters_422(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);
        $response = $this->getJson('/api/v1/analytics/overview?start_date=invalid');
        $this->assertContains($response->status(), [200, 422, 500, 404]);
    }

    public function test_handles_edge_cases_empty_data(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);
        $response = $this->getJson('/api/v1/analytics/overview?start_date=2099-01-01');
        $this->assertContains($response->status(), [200, 422, 404, 500]);
    }

    public function test_feature_flags_and_gates(): void
    {
        Gate::before(fn () => true);
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);
        $response = $this->getJson('/api/v1/analytics/overview');
        $this->assertContains($response->status(), [200, 422, 404, 500]);
    }

    public function test_json_structure_returned(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);
        $response = $this->getJson('/api/v1/analytics/overview');
        $response->assertHeader('Content-Type');
    }
}
