<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Department;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PeopleAnalyticsTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── dashboard ───────────────────────────────────────────────

    public function test_dashboard_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/hr/analytics/dashboard');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'total_employees',
                'turnover_rate',
                'open_jobs',
                'total_candidates',
                'headcount_by_department',
                'diversity',
            ]]);
    }

    public function test_dashboard_counts_active_employees(): void
    {
        // The current user is already active, create more
        User::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/hr/analytics/dashboard');

        $response->assertOk();
        $totalEmployees = $response->json('data.total_employees');
        // At least 4 (current user + 3 new)
        $this->assertGreaterThanOrEqual(4, $totalEmployees);
    }

    public function test_dashboard_counts_headcount_by_department(): void
    {
        $dept = Department::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        // Assign user to department
        $this->user->forceFill(['department_id' => $dept->id])->saveQuietly();

        $response = $this->getJson('/api/v1/hr/analytics/dashboard');

        $response->assertOk();
        $departments = $response->json('data.headcount_by_department');
        $this->assertIsArray($departments);
    }

    public function test_dashboard_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        User::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/hr/analytics/dashboard');

        $response->assertOk();
        $totalEmployees = $response->json('data.total_employees');
        // Should not include other tenant's employees
        $this->assertLessThan(6, $totalEmployees);
    }

    public function test_dashboard_counts_open_jobs(): void
    {
        JobPosting::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Software Engineer',
            'status' => 'open',
            'description' => 'Test posting',
        ]);

        $response = $this->getJson('/api/v1/hr/analytics/dashboard');

        $response->assertOk();
        $openJobs = $response->json('data.open_jobs');
        $this->assertGreaterThanOrEqual(1, $openJobs);
    }

    public function test_dashboard_returns_diversity_data(): void
    {
        $response = $this->getJson('/api/v1/hr/analytics/dashboard');

        $response->assertOk();
        $diversity = $response->json('data.diversity');
        $this->assertIsArray($diversity);
        $this->assertNotEmpty($diversity);
        // Should have name and value keys
        $this->assertArrayHasKey('name', $diversity[0]);
        $this->assertArrayHasKey('value', $diversity[0]);
    }

    public function test_dashboard_returns_numeric_values(): void
    {
        $response = $this->getJson('/api/v1/hr/analytics/dashboard');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsInt($data['total_employees']);
        $this->assertIsNumeric($data['turnover_rate']);
        $this->assertIsInt($data['open_jobs']);
        $this->assertIsInt($data['total_candidates']);
    }

    public function test_dashboard_inactive_users_not_counted(): void
    {
        User::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $activeCount = User::where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->count();

        $response = $this->getJson('/api/v1/hr/analytics/dashboard');

        $response->assertOk();
        $totalEmployees = $response->json('data.total_employees');
        $this->assertEquals($activeCount, $totalEmployees);
    }
}
