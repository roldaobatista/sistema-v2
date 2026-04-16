<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportFormRequestTest extends TestCase
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

    public function test_fails_when_to_date_is_before_from_date(): void
    {
        $response = $this->getJson('/api/v1/reports/work-orders?from=2025-12-31&to=2025-01-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    public function test_fails_when_branch_id_is_invalid(): void
    {
        $response = $this->getJson('/api/v1/reports/work-orders?from=2025-01-01&to=2025-12-31&branch_id=999999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_passes_with_valid_dates_and_branch(): void
    {
        $branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/reports/work-orders?from=2025-01-01&to=2025-12-31&branch_id='.$branch->id);

        $response->assertStatus(200);
    }
}
