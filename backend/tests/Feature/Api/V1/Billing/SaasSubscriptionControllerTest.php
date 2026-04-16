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

class SaasSubscriptionControllerTest extends TestCase
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

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_subscriptions(): void
    {
        // 2 subscriptions do tenant atual
        $currentSubscriptions = SaasSubscription::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // 3 subscriptions de OUTRO tenant (nao podem vazar)
        $otherTenant = Tenant::factory()->create();
        $foreignSubscriptions = SaasSubscription::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->getJson('/api/v1/billing/subscriptions');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'tenant_id', 'status'],
                ],
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $this->assertEqualsCanonicalizing(
            $currentSubscriptions->pluck('id')->map(fn ($id) => (int) $id)->all(),
            array_map('intval', array_column($data, 'id'))
        );
        $this->assertSame(
            array_fill(0, 2, $this->tenant->id),
            array_map('intval', array_column($data, 'tenant_id')),
            'Subscription de outro tenant vazou'
        );
        $this->assertEmpty(array_intersect(
            $foreignSubscriptions->pluck('id')->map(fn ($id) => (int) $id)->all(),
            array_map('intval', array_column($data, 'id'))
        ));
    }

    public function test_index_filters_by_status(): void
    {
        SaasSubscription::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        SaasSubscription::factory()->cancelled()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/billing/subscriptions?status=active');
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'tenant_id', 'status'],
                ],
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertSame(
            array_fill(0, 2, 'active'),
            array_column($data, 'status')
        );
    }

    public function test_show_returns_404_for_cross_tenant_subscription(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignSub = SaasSubscription::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->getJson("/api/v1/billing/subscriptions/{$foreignSub->id}");

        $response->assertNotFound();
    }

    public function test_store_validates_required_plan_id(): void
    {
        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_store_rejects_nonexistent_plan(): void
    {
        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => 99999,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_store_rejects_invalid_billing_cycle(): void
    {
        $plan = SaasPlan::factory()->create();

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'weekly', // invalid — so monthly/annual
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billing_cycle']);
    }

    public function test_store_rejects_invalid_payment_gateway(): void
    {
        $plan = SaasPlan::factory()->create();

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'payment_gateway' => 'bitcoin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_gateway']);
    }
}
