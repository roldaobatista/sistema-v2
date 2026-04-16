<?php

namespace Tests\Feature\Api\V1\Crm;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmSalesGoal;
use App\Models\CrmTerritory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmTerritoryGoalControllerTest extends TestCase
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

    private function createTerritory(?int $tenantId = null, string $name = 'Sul'): CrmTerritory
    {
        return CrmTerritory::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'description' => 'Território de teste',
            'regions' => ['RS', 'SC', 'PR'],
            'is_active' => true,
        ]);
    }

    public function test_territories_returns_only_current_tenant(): void
    {
        $mine = $this->createTerritory();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createTerritory($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/crm-features/territories');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_territory_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm-features/territories', []);

        $response->assertStatus(422);
    }

    public function test_store_territory_creates_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/crm-features/territories', [
            'name' => 'Nordeste',
            'description' => 'Área comercial NE',
            'regions' => ['BA', 'PE', 'CE'],
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('crm_territories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Nordeste',
        ]);
    }

    public function test_sales_goals_returns_only_current_tenant(): void
    {
        CrmSalesGoal::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'target_revenue' => 50000,
            'target_deals' => 10,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        CrmSalesGoal::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'target_revenue' => 777777,
            'target_deals' => 99,
        ]);

        $response = $this->getJson('/api/v1/crm-features/goals');

        $response->assertOk()->assertJsonStructure(['data']);
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('777777', $json);
    }

    public function test_store_sales_goal_validates_period_end_after_start(): void
    {
        $response = $this->postJson('/api/v1/crm-features/goals', [
            'user_id' => $this->user->id,
            'period_type' => 'monthly',
            'period_start' => '2026-05-01',
            'period_end' => '2026-04-30',
            'target_revenue' => 10000,
            'target_deals' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_goals_dashboard_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/crm-features/goals/dashboard');

        $response->assertOk();
    }
}
