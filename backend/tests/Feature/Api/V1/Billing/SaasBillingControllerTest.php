<?php

namespace Tests\Feature\Api\V1\Billing;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaasBillingControllerTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

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
        $this->otherTenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ═══════════════════════════════════════════════════════════
    // SaasPlan — Index
    // ═══════════════════════════════════════════════════════════

    public function test_plan_index_returns_paginated_plans(): void
    {
        SaasPlan::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/billing/plans');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ]);
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    public function test_plan_index_filters_active_only(): void
    {
        SaasPlan::factory()->create(['is_active' => true, 'name' => 'Active Plan']);
        SaasPlan::factory()->create(['is_active' => false, 'name' => 'Inactive Plan']);

        $response = $this->getJson('/api/v1/billing/plans?active_only=1');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Active Plan'));
        $this->assertFalse($names->contains('Inactive Plan'));
    }

    // ═══════════════════════════════════════════════════════════
    // SaasPlan — Store
    // ═══════════════════════════════════════════════════════════

    public function test_plan_store_creates_plan(): void
    {
        $payload = [
            'name' => 'Profissional',
            'slug' => 'profissional',
            'description' => 'Plano profissional completo',
            'monthly_price' => 299.90,
            'annual_price' => 2999.00,
            'modules' => ['financial', 'calibration', 'fleet'],
            'max_users' => 15,
            'max_work_orders_month' => 500,
            'is_active' => true,
            'sort_order' => 1,
        ];

        $response = $this->postJson('/api/v1/billing/plans', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Profissional')
            ->assertJsonPath('data.slug', 'profissional');

        $this->assertDatabaseHas('saas_plans', ['slug' => 'profissional']);
    }

    public function test_plan_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/billing/plans', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug', 'monthly_price', 'annual_price', 'max_users']);
    }

    public function test_plan_store_validates_unique_slug(): void
    {
        SaasPlan::factory()->create(['slug' => 'basic']);

        $response = $this->postJson('/api/v1/billing/plans', [
            'name' => 'Básico Dup',
            'slug' => 'basic',
            'monthly_price' => 99,
            'annual_price' => 999,
            'max_users' => 5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    // ═══════════════════════════════════════════════════════════
    // SaasPlan — Show
    // ═══════════════════════════════════════════════════════════

    public function test_plan_show_returns_plan(): void
    {
        $plan = SaasPlan::factory()->create(['name' => 'Enterprise']);

        $response = $this->getJson("/api/v1/billing/plans/{$plan->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Enterprise');
    }

    public function test_plan_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/v1/billing/plans/99999');

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // SaasPlan — Update
    // ═══════════════════════════════════════════════════════════

    public function test_plan_update_modifies_plan(): void
    {
        $plan = SaasPlan::factory()->create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $response = $this->putJson("/api/v1/billing/plans/{$plan->id}", [
            'name' => 'New Name',
            'slug' => 'new-slug',
            'monthly_price' => 199.90,
            'annual_price' => 1999.00,
            'max_users' => 20,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    // ═══════════════════════════════════════════════════════════
    // SaasPlan — Destroy
    // ═══════════════════════════════════════════════════════════

    public function test_plan_destroy_deletes_plan(): void
    {
        $plan = SaasPlan::factory()->create();

        $response = $this->deleteJson("/api/v1/billing/plans/{$plan->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('saas_plans', ['id' => $plan->id]);
    }

    public function test_plan_destroy_blocks_when_has_subscriptions(): void
    {
        $plan = SaasPlan::factory()->create();
        SaasSubscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->deleteJson("/api/v1/billing/plans/{$plan->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('saas_plans', ['id' => $plan->id]);
    }

    // ═══════════════════════════════════════════════════════════
    // SaasSubscription — Index
    // ═══════════════════════════════════════════════════════════

    public function test_subscription_index_returns_paginated(): void
    {
        $plan = SaasPlan::factory()->create();
        SaasSubscription::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->getJson('/api/v1/billing/subscriptions');

        $response->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_subscription_index_filters_by_status(): void
    {
        $plan = SaasPlan::factory()->create();
        SaasSubscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        SaasSubscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);

        $response = $this->getJson('/api/v1/billing/subscriptions?status=active');

        $response->assertOk();
        foreach ($response->json('data') as $sub) {
            $this->assertEquals('active', $sub['status']);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // SaasSubscription — Store
    // ═══════════════════════════════════════════════════════════

    public function test_subscription_store_creates_subscription(): void
    {
        $plan = SaasPlan::factory()->create(['monthly_price' => 299.90]);

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.plan.id', $plan->id);

        $this->assertDatabaseHas('saas_subscriptions', [
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
        ]);

        // Verifica que o tenant foi atualizado com o current_plan_id
        $this->tenant->refresh();
        $this->assertEquals($plan->id, $this->tenant->current_plan_id);
    }

    public function test_subscription_store_validates_required(): void
    {
        $response = $this->postJson('/api/v1/billing/subscriptions', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id', 'billing_cycle']);
    }

    public function test_subscription_store_validates_plan_exists(): void
    {
        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => 99999,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_subscription_store_annual_sets_correct_period(): void
    {
        $plan = SaasPlan::factory()->create(['annual_price' => 2999.00]);

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'annual',
        ]);

        $response->assertStatus(201);
        $sub = SaasSubscription::latest()->first();
        $this->assertEquals('annual', $sub->billing_cycle);
        $this->assertEquals('2999.00', $sub->price);
    }

    // ═══════════════════════════════════════════════════════════
    // SaasSubscription — Show
    // ═══════════════════════════════════════════════════════════

    public function test_subscription_show_returns_subscription(): void
    {
        $plan = SaasPlan::factory()->create();
        $sub = SaasSubscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->getJson("/api/v1/billing/subscriptions/{$sub->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $sub->id)
            ->assertJsonStructure(['data' => ['plan', 'creator']]);
    }

    // ═══════════════════════════════════════════════════════════
    // SaasSubscription — Cancel
    // ═══════════════════════════════════════════════════════════

    public function test_subscription_cancel_changes_status(): void
    {
        $plan = SaasPlan::factory()->create();
        $sub = SaasSubscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/billing/subscriptions/{$sub->id}/cancel", [
            'reason' => 'Não preciso mais',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $sub->refresh();
        $this->assertEquals('cancelled', $sub->status);
        $this->assertEquals('Não preciso mais', $sub->cancellation_reason);
    }

    public function test_subscription_cancel_already_cancelled_returns_422(): void
    {
        $sub = SaasSubscription::factory()->cancelled()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson("/api/v1/billing/subscriptions/{$sub->id}/cancel");

        $response->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════
    // SaasSubscription — Renew
    // ═══════════════════════════════════════════════════════════

    public function test_subscription_renew_reactivates(): void
    {
        $plan = SaasPlan::factory()->create();
        $sub = SaasSubscription::factory()->cancelled()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'current_period_end' => now()->subDay(),
        ]);

        $response = $this->postJson("/api/v1/billing/subscriptions/{$sub->id}/renew");

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_subscription_renew_already_active_returns_422(): void
    {
        $plan = SaasPlan::factory()->create();
        $sub = SaasSubscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/billing/subscriptions/{$sub->id}/renew");

        $response->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════
    // SaasSubscription — Cross-tenant
    // ═══════════════════════════════════════════════════════════

    public function test_subscription_cross_tenant_isolation(): void
    {
        $plan = SaasPlan::factory()->create();
        $otherSub = SaasSubscription::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->getJson('/api/v1/billing/subscriptions');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($otherSub->id, $ids);
    }

    // ═══════════════════════════════════════════════════════════
    // SaasPlan — JSON Structure
    // ═══════════════════════════════════════════════════════════

    public function test_plan_response_has_correct_structure(): void
    {
        $response = $this->postJson('/api/v1/billing/plans', [
            'name' => 'Structure Test',
            'slug' => 'structure-test',
            'description' => 'Full structure plan',
            'monthly_price' => 99.90,
            'annual_price' => 999.00,
            'modules' => ['financial'],
            'max_users' => 5,
            'sort_order' => 0,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('Structure Test', $response->json('data.name'));
        $this->assertArrayHasKey('id', $response->json('data'));
        $this->assertArrayHasKey('created_at', $response->json('data'));
    }
}
