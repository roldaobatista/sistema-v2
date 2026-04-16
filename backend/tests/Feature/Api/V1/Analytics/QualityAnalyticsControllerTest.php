<?php

namespace Tests\Feature\Api\V1\Analytics;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\QualityProcedure;
use App\Models\SatisfactionSurvey;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QualityAnalyticsControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([EnsureTenantScope::class]);
        // DO NOT disable CheckPermission middleware if we want to test 403, but in this architecture,
        // usually we disable it and test the middleware separately, OR we keep it and act as a user with no perms.
        // Let's just disable it to match AnalyticsTest, but for the 403 test we can re-enable it.

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    public function test_quality_analytics_returns_expected_structure(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        $response = $this->getJson('/api/v1/analytics/quality/analytics');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'conformity_index',
                'nps_trend',
            ]]);
    }

    public function test_quality_analytics_accepts_period_param(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        SatisfactionSurvey::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nps_score' => 10,
            'created_at' => now()->subMonths(5), // Inside 6 months
        ]);

        SatisfactionSurvey::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nps_score' => 10,
            'created_at' => now()->subMonths(10), // Outside 6 months, but inside 12
        ]);

        // Default is 6 months
        $response6 = $this->getJson('/api/v1/analytics/quality/analytics');
        $response6->assertOk();

        // Custom period is 12 months
        $response12 = $this->getJson('/api/v1/analytics/quality/analytics?period=12');
        $response12->assertOk();

        $data6 = $response6->json('data.nps_trend');
        $data12 = $response12->json('data.nps_trend');

        $this->assertLessThan(count($data12), count($data6));
    }

    public function test_quality_analytics_calculates_conformity_index(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        QualityProcedure::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        QualityProcedure::factory()->count(1)->create(['tenant_id' => $this->tenant->id, 'status' => 'obsolete']);

        $response = $this->getJson('/api/v1/analytics/quality/analytics');

        $response->assertOk();
        $this->assertEquals(75.0, $response->json('data.conformity_index'));
    }

    public function test_quality_analytics_calculates_nps_trend(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        $monthStr = now()->format('Y-m');

        SatisfactionSurvey::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'nps_score' => 9, 'created_at' => now()]); // Promoters
        SatisfactionSurvey::factory()->count(1)->create(['tenant_id' => $this->tenant->id, 'nps_score' => 7, 'created_at' => now()]); // Passives
        SatisfactionSurvey::factory()->count(1)->create(['tenant_id' => $this->tenant->id, 'nps_score' => 2, 'created_at' => now()]); // Detractors

        $response = $this->getJson('/api/v1/analytics/quality/analytics');

        $response->assertOk();
        $trend = collect($response->json('data.nps_trend'))->firstWhere('month', $monthStr);

        $this->assertNotNull($trend);
        $this->assertEquals(4, $trend['total']);
        $this->assertEquals(2, $trend['promoters']);
        $this->assertEquals(1, $trend['detractors']);
        // NPS = (Promoters - Detractors) / Total * 100 = (2 - 1) / 4 * 100 = 25
        $this->assertEquals(25.0, $trend['nps']);
    }

    public function test_quality_analytics_tenant_isolation(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        $otherTenant = Tenant::factory()->create();

        QualityProcedure::factory()->count(5)->create(['tenant_id' => $otherTenant->id, 'status' => 'active']);

        $response = $this->getJson('/api/v1/analytics/quality/analytics');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.conformity_index'));
    }

    public function test_quality_analytics_requires_authentication(): void
    {
        // No Sanctum::actingAs
        $response = $this->getJson('/api/v1/analytics/quality/analytics');
        $response->assertUnauthorized();
    }

    public function test_quality_analytics_handles_empty_data_gracefully(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        $response = $this->getJson('/api/v1/analytics/quality/analytics');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.conformity_index'));
        $this->assertEmpty($response->json('data.nps_trend'));
    }

    public function test_quality_analytics_respects_permissions_403(): void
    {
        Gate::before(fn () => false); // Deny all

        Sanctum::actingAs($this->user, ['*']);
        // We DO NOT skip the CheckPermission middleware here

        $response = $this->getJson('/api/v1/analytics/quality/analytics');

        $response->assertForbidden();
    }
}
