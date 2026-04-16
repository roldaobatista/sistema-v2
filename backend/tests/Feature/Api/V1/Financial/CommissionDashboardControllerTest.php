<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionDashboardControllerTest extends TestCase
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

    private function createCommissionEvent(?int $tenantId = null, float $amount = 500.00, string $status = 'approved'): CommissionEvent
    {
        $tid = $tenantId ?? $this->tenant->id;
        $rule = CommissionRule::create([
            'tenant_id' => $tid,
            'name' => 'Regra '.uniqid(),
            'type' => 'percentage',
            'value' => 5.0,
            'applies_to' => 'revenue',
            'calculation_type' => 'percentage',
            'active' => true,
        ]);

        $wo = WorkOrder::factory()->create(['tenant_id' => $tid]);

        return CommissionEvent::create([
            'tenant_id' => $tid,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
            'base_amount' => 10000,
            'commission_amount' => $amount,
            'proportion' => 1.0,
            'status' => $status,
        ]);
    }

    public function test_overview_returns_aggregated_data(): void
    {
        $this->createCommissionEvent();

        $response = $this->getJson('/api/v1/commission-dashboard/overview');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_ranking_returns_top_performers(): void
    {
        $this->createCommissionEvent(null, 1000.00);

        $response = $this->getJson('/api/v1/commission-dashboard/ranking');

        $response->assertOk();
    }

    public function test_evolution_returns_time_series(): void
    {
        $this->createCommissionEvent();

        $response = $this->getJson('/api/v1/commission-dashboard/evolution');

        $response->assertOk();
    }

    public function test_by_rule_returns_breakdown(): void
    {
        $this->createCommissionEvent();

        $response = $this->getJson('/api/v1/commission-dashboard/by-rule');

        $response->assertOk();
    }

    public function test_by_role_returns_breakdown(): void
    {
        $this->createCommissionEvent();

        $response = $this->getJson('/api/v1/commission-dashboard/by-role');

        $response->assertOk();
    }

    public function test_overview_isolates_other_tenant_data(): void
    {
        $otherTenant = Tenant::factory()->create();
        $this->createCommissionEvent($otherTenant->id, 888888.00);

        $response = $this->getJson('/api/v1/commission-dashboard/overview');

        $response->assertOk();
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('888888', $json);
    }
}
