<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
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

    public function test_widgets_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/hr/dashboard/widgets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'employees_clocked_in',
                    'pending_adjustments',
                    'pending_leaves',
                    'expiring_documents_30d',
                    'birthdays_this_month',
                ],
            ]);
    }

    public function test_team_returns_subordinates(): void
    {
        $response = $this->getJson('/api/v1/hr/dashboard/team');

        $response->assertStatus(200);
    }
}
