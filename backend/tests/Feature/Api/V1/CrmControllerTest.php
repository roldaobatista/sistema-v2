<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmPipeline;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmControllerTest extends TestCase
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

    public function test_dashboard_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/crm/dashboard');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_constants_returns_metadata(): void
    {
        $response = $this->getJson('/api/v1/crm/constants');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_pipelines_index_returns_only_current_tenant(): void
    {
        CrmPipeline::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pipeline Vendas',
            'slug' => 'pipeline-vendas-'.uniqid(),
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $otherTenant = Tenant::factory()->create();
        CrmPipeline::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'LEAK Pipeline',
            'slug' => 'leak-pipeline-'.uniqid(),
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/v1/crm/pipelines');

        $response->assertOk()->assertJsonStructure(['data']);
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK Pipeline', $json);
    }

    public function test_pipelines_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm/pipelines', []);

        $response->assertStatus(422);
    }

    public function test_deals_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/crm/deals');

        $response->assertOk();
    }

    public function test_activities_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/crm/activities');

        $response->assertOk();
    }

    public function test_deals_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm/deals', []);

        $response->assertStatus(422);
    }
}
