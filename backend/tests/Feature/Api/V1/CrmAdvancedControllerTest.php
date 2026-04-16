<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmAdvancedControllerTest extends TestCase
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

    public function test_funnel_automations_returns_list(): void
    {
        $response = $this->getJson('/api/v1/crm-advanced/funnel-automations');

        $response->assertOk();
    }

    public function test_store_funnel_automation_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm-advanced/funnel-automations', []);

        $response->assertStatus(422);
    }

    public function test_sales_forecast_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/crm-advanced/forecast');

        $response->assertOk();
    }

    public function test_find_duplicate_leads_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/crm-advanced/leads/duplicates');

        $response->assertOk();
    }

    public function test_multi_product_pipelines_returns_list(): void
    {
        $response = $this->getJson('/api/v1/crm-advanced/pipelines');

        $response->assertOk();
    }

    public function test_recalculate_lead_scores_is_reachable(): void
    {
        $response = $this->postJson('/api/v1/crm-advanced/lead-scoring/recalculate');

        $this->assertContains($response->status(), [200, 201, 202, 422]);
    }
}
