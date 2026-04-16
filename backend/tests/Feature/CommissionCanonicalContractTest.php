<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommissionCanonicalContractTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'commissions.rule.view',
            'commissions.event.view',
            'commissions.settlement.view',
            'commissions.dispute.view',
            'commissions.goal.view',
            'commissions.campaign.view',
            'commissions.recurring.view',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->user->givePermissionTo([
            'commissions.rule.view',
            'commissions.event.view',
            'commissions.settlement.view',
            'commissions.dispute.view',
            'commissions.goal.view',
            'commissions.campaign.view',
            'commissions.recurring.view',
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_financial_commissions_frontend_endpoints_keep_expected_shapes(): void
    {
        $this->getJson('/api/v1/commission-rules')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/commission-events')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/commission-settlements')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/commission-disputes')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/commission-goals')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/commission-campaigns')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/recurring-commissions')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_dashboard_and_self_service_endpoints_keep_expected_shapes(): void
    {
        $this->getJson('/api/v1/commission-dashboard/overview')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pending',
                    'approved',
                    'paid_this_month',
                    'paid_last_month',
                    'variation_pct',
                    'total_events',
                    'events_count',
                    'total_rules',
                ],
            ]);

        $this->getJson('/api/v1/commission-dashboard/ranking')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/commission-dashboard/evolution')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/commission-dashboard/by-rule')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/commission-dashboard/by-role')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/my/commission-events')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/my/commission-settlements')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/my/commission-disputes')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/my/commission-summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_month',
                    'pending',
                    'paid',
                ],
            ]);
    }
}
