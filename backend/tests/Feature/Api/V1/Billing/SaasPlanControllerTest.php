<?php

namespace Tests\Feature\Api\V1\Billing;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\SaasPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaasPlanControllerTest extends TestCase
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

    public function test_index_returns_paginated_plans(): void
    {
        SaasPlan::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/billing/plans');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_show_returns_plan_by_id(): void
    {
        $plan = SaasPlan::factory()->create([
            'name' => 'Plano Premium',
            'monthly_price' => 299.00,
        ]);

        $response = $this->getJson("/api/v1/billing/plans/{$plan->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonPath('data.name', 'Plano Premium')
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('name');
    }

    public function test_show_returns_404_for_inexistent_plan(): void
    {
        $response = $this->getJson('/api/v1/billing/plans/999999');

        $response->assertStatus(404);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/billing/plans', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'slug',
                'monthly_price',
                'annual_price',
                'max_users',
            ]);
    }

    public function test_store_creates_plan_successfully(): void
    {
        $response = $this->postJson('/api/v1/billing/plans', [
            'name' => 'Plano Teste Lote4',
            'slug' => 'plano-teste-lote4',
            'description' => 'Plano criado em teste',
            'monthly_price' => 199.90,
            'annual_price' => 1999.00,
            'modules' => ['financial', 'calibration'],
            'max_users' => 10,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'plano-teste-lote4')
            ->assertJsonMissingPath('slug')
            ->assertJsonMissingPath('name');

        $this->assertDatabaseHas('saas_plans', [
            'slug' => 'plano-teste-lote4',
            'name' => 'Plano Teste Lote4',
        ]);
    }

    public function test_destroy_removes_plan(): void
    {
        $plan = SaasPlan::factory()->create();

        $response = $this->deleteJson("/api/v1/billing/plans/{$plan->id}");

        $this->assertContains($response->status(), [200, 204], 'Destroy deve ter sucesso');
        $this->assertDatabaseMissing('saas_plans', ['id' => $plan->id]);
    }
}
