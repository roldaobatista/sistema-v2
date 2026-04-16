<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmDeal;
use App\Models\CrmDealCompetitor;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\CrmReferral;
use App\Models\CrmSequence;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmFeaturesCompatibilityTest extends TestCase
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
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_referral_stats_and_options_return_frontend_compatible_payloads(): void
    {
        $customerA = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $deal = $this->createDeal($this->tenant->id, $customerA->id, 'Negocio de Indicacao');

        CrmReferral::create([
            'tenant_id' => $this->tenant->id,
            'referrer_customer_id' => $customerA->id,
            'referred_customer_id' => $customerB->id,
            'deal_id' => $deal->id,
            'referred_name' => 'Contato B',
            'status' => 'converted',
            'reward_type' => 'credit',
            'reward_value' => 200,
            'reward_given' => true,
        ]);

        CrmReferral::create([
            'tenant_id' => $this->tenant->id,
            'referrer_customer_id' => $customerA->id,
            'referred_name' => 'Contato C',
            'status' => 'pending',
            'reward_type' => null,
            'reward_value' => null,
            'reward_given' => false,
        ]);

        $this->getJson('/api/v1/crm-features/referrals/stats')
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'converted',
                'conversion_rate',
                'total_reward_value',
                'total_rewards',
                'top_referrers' => [
                    ['id', 'name', 'count', 'converted_count'],
                ],
            ])
            ->assertJsonPath('data.total_rewards', 200)
            ->assertJsonPath('data.top_referrers.0.name', $customerA->name);

        $this->getJson('/api/v1/crm-features/referrals/options')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'customers' => [['id', 'name']],
                'deals' => [['id', 'title', 'status', 'value']],
            ]]);
    }

    public function test_referral_and_competitor_delete_are_tenant_scoped(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $deal = $this->createDeal($this->tenant->id, $customer->id, 'Deal Interno');

        $referral = CrmReferral::create([
            'tenant_id' => $this->tenant->id,
            'referrer_customer_id' => $customer->id,
            'referred_name' => 'Lead Interno',
            'status' => 'pending',
            'reward_given' => false,
        ]);

        $competitor = CrmDealCompetitor::create([
            'deal_id' => $deal->id,
            'competitor_name' => 'Concorrente Interno',
            'competitor_price' => 1000,
            'outcome' => 'unknown',
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDeal = $this->createDeal($otherTenant->id, $otherCustomer->id, 'Deal Externo');
        $otherReferralId = (int) DB::table('crm_referrals')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'referrer_customer_id' => $otherCustomer->id,
            'referred_name' => 'Lead Externo',
            'status' => 'pending',
            'reward_given' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherCompetitorId = (int) DB::table('crm_deal_competitors')->insertGetId([
            'deal_id' => $otherDeal->id,
            'competitor_name' => 'Concorrente Externo',
            'competitor_price' => 1500,
            'outcome' => 'lost',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/api/v1/crm-features/referrals/{$referral->id}")
            ->assertOk();
        $this->assertDatabaseMissing('crm_referrals', ['id' => $referral->id]);

        $this->deleteJson("/api/v1/crm-features/competitors/{$competitor->id}")
            ->assertOk();
        $this->assertDatabaseMissing('crm_deal_competitors', ['id' => $competitor->id]);

        $this->deleteJson("/api/v1/crm-features/referrals/{$otherReferralId}")
            ->assertStatus(404);
        $this->assertDatabaseHas('crm_referrals', ['id' => $otherReferralId]);

        $this->deleteJson("/api/v1/crm-features/competitors/{$otherCompetitorId}")
            ->assertStatus(404);
        $this->assertDatabaseHas('crm_deal_competitors', ['id' => $otherCompetitorId]);
    }

    public function test_sequence_enrollments_endpoint_returns_list(): void
    {
        $sequence = CrmSequence::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cadência Teste',
            'description' => null,
            'total_steps' => 0,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/crm-features/sequences/{$sequence->id}/enrollments");

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data', []);
    }

    public function test_tracking_stats_endpoint_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/crm-features/tracking/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_events',
                    'by_type',
                ],
            ]);
    }

    private function createDeal(int $tenantId, int $customerId, string $title): CrmDeal
    {
        $pipeline = CrmPipeline::create([
            'tenant_id' => $tenantId,
            'name' => "Pipeline {$title}",
            'slug' => strtolower(str_replace(' ', '-', $title)).'-'.uniqid(),
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $stage = CrmPipelineStage::create([
            'tenant_id' => $tenantId,
            'pipeline_id' => $pipeline->id,
            'name' => 'Prospecao',
            'sort_order' => 0,
            'probability' => 30,
            'is_won' => false,
            'is_lost' => false,
        ]);

        return CrmDeal::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'title' => $title,
            'value' => 4500,
            'probability' => 30,
            'status' => 'open',
        ]);
    }
}
