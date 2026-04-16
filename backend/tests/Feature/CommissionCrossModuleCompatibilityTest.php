<?php

namespace Tests\Feature;

use App\Enums\CommissionEventStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionCampaign;
use App\Models\CommissionEvent;
use App\Models\CommissionGoal;
use App\Models\CommissionRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionCrossModuleCompatibilityTest extends TestCase
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

    public function test_my_goals_filter_returns_only_authenticated_user_goals(): void
    {
        CommissionGoal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => '2026-03',
        ]);

        CommissionGoal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'period' => '2026-03',
        ]);

        $response = $this->getJson('/api/v1/commission-goals?my=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->user->id);
    }

    public function test_active_campaign_filter_returns_only_currently_active_campaigns(): void
    {
        CommissionCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Campanha Ativa',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'active' => true,
        ]);

        CommissionCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Campanha Inativa',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'active' => true,
        ]);

        $response = $this->getJson('/api/v1/commission-campaigns?active=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Campanha Ativa');
    }

    public function test_dashboard_ranking_returns_list_payload_for_cross_module_consumers(): void
    {
        $rule = CommissionRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'active' => true,
        ]);

        CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'commission_rule_id' => $rule->id,
            'status' => CommissionEventStatus::APPROVED->value,
            'commission_amount' => 150.50,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/commission-dashboard/ranking');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'total', 'events_count', 'position'],
                ],
            ]);
    }
}
