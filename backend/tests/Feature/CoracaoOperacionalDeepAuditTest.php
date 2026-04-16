<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Deep audit tests — CRM Deals, Pipelines, Activities, and Customer Merge.
 *
 * Covers: tenant isolation, CRUD, status machines, validação,
 * mark-won/lost, and cross-tenant security.
 */
class CoracaoOperacionalDeepAuditTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    private Customer $customerA;

    private CrmPipeline $pipelineA;

    private CrmPipelineStage $stageA;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenantA = Tenant::factory()->create(['name' => 'CrmTenantA', 'status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['name' => 'CrmTenantB', 'status' => 'active']);

        $this->adminA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'email' => 'admin@crm-a.test',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->adminA->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        $this->adminB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'email' => 'admin@crm-b.test',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->adminB->tenants()->attach($this->tenantB->id, ['is_default' => true]);

        $this->customerA = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->pipelineA = CrmPipeline::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->stageA = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'pipeline_id' => $this->pipelineA->id,
        ]);

        app()->instance('current_tenant_id', $this->tenantA->id);
    }

    // ══════════════════════════════════════════════════════════════
    // ── CRM DEALS — CRM-01 a CRM-12
    // ══════════════════════════════════════════════════════════════

    /** CRM-01: Unauthenticated request must return 401 */
    public function test_crm_deals_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/crm/deals')->assertUnauthorized();
    }

    /** CRM-02: Index only returns deals of the authenticated user's tenant */
    public function test_crm_deals_list_only_own_tenant(): void
    {
        CrmDeal::factory(3)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
        ]);

        $pipelineB = CrmPipeline::factory()->create(['tenant_id' => $this->tenantB->id]);
        $stageB = CrmPipelineStage::factory()->create(['tenant_id' => $this->tenantB->id, 'pipeline_id' => $pipelineB->id]);
        CrmDeal::factory(2)->create(['tenant_id' => $this->tenantB->id, 'pipeline_id' => $pipelineB->id, 'stage_id' => $stageB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson('/api/v1/crm/deals')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /** CRM-03: Store without required fields must fail with specific validation errors */
    public function test_crm_deals_store_requires_required_fields(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/crm/deals', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'pipeline_id', 'stage_id', 'title']);
    }

    /** CRM-04: Store with customer from another tenant must fail */
    public function test_crm_deals_store_with_other_tenant_customer_fails(): void
    {
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/crm/deals', [
            'customer_id' => $customerB->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
            'title' => 'Deal Inválido',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    /** CRM-05: Successful store always creates deal with STATUS_OPEN */
    public function test_crm_deals_store_creates_with_open_status(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->postJson('/api/v1/crm/deals', [
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
            'title' => 'Calibração Trimestral 2026',
            'value' => 12500.00,
            'probability' => 70,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('crm_deals', [
            'customer_id' => $this->customerA->id,
            'tenant_id' => $this->tenantA->id,
            'status' => CrmDeal::STATUS_OPEN,
            'title' => 'Calibração Trimestral 2026',
        ]);
    }

    /** CRM-06: Show of a deal from another tenant must return 404 */
    public function test_crm_deals_show_cross_tenant_returns_404(): void
    {
        $pipelineB = CrmPipeline::factory()->create(['tenant_id' => $this->tenantB->id]);
        $stageB = CrmPipelineStage::factory()->create(['tenant_id' => $this->tenantB->id, 'pipeline_id' => $pipelineB->id]);
        $dealB = CrmDeal::factory()->create(['tenant_id' => $this->tenantB->id, 'pipeline_id' => $pipelineB->id, 'stage_id' => $stageB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson("/api/v1/crm/deals/{$dealB->id}")
            ->assertNotFound();
    }

    /** CRM-07: Mark as won must change status, set probability=100, set won_at */
    public function test_crm_deals_mark_as_won_succeeds(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->putJson("/api/v1/crm/deals/{$deal->id}/won")
            ->assertOk();

        $fresh = $deal->fresh();
        $this->assertEquals(CrmDeal::STATUS_WON, $fresh->status);
        $this->assertEquals(100, $fresh->probability);
        $this->assertNotNull($fresh->won_at);
    }

    /** CRM-08: Marking an already-won deal as won must be blocked (422) */
    public function test_crm_deals_mark_already_won_returns_422(): void
    {
        $deal = CrmDeal::factory()->won()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->putJson("/api/v1/crm/deals/{$deal->id}/won")
            ->assertUnprocessable();
    }

    /** CRM-09: Mark as lost with reason must change status and set probability=0 */
    public function test_crm_deals_mark_as_lost_with_reason(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->putJson("/api/v1/crm/deals/{$deal->id}/lost", [
            'lost_reason' => 'Preço acima do orçamento do cliente',
        ])->assertOk();

        $this->assertDatabaseHas('crm_deals', [
            'id' => $deal->id,
            'status' => CrmDeal::STATUS_LOST,
            'probability' => 0,
            'lost_reason' => 'Preço acima do orçamento do cliente',
        ]);
    }

    /** CRM-10: Delete deal must soft-delete it (204) */
    public function test_crm_deals_delete_succeeds(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->deleteJson("/api/v1/crm/deals/{$deal->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('crm_deals', ['id' => $deal->id]);
    }

    /** CRM-11: Dashboard must return all expected aggregation keys */
    public function test_crm_dashboard_returns_correct_structure(): void
    {
        CrmDeal::factory(2)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson('/api/v1/crm/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'period',
                'kpis',
                'pipelines',
            ]]);
    }

    /** CRM-12: Update stage must move the deal to the new stage */
    public function test_crm_deals_update_stage_succeeds(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
        ]);

        $newStage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'pipeline_id' => $this->pipelineA->id,
            'name' => 'Proposta Enviada',
            'sort_order' => 2,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->putJson("/api/v1/crm/deals/{$deal->id}/stage", [
            'stage_id' => $newStage->id,
        ])->assertOk();

        $this->assertDatabaseHas('crm_deals', [
            'id' => $deal->id,
            'stage_id' => $newStage->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // ── CRM ACTIVITIES — CRM-13 a CRM-15
    // ══════════════════════════════════════════════════════════════

    /** CRM-13: Store activity without required fields must fail */
    public function test_crm_activities_store_requires_type_and_title(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/crm/activities', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'customer_id', 'title']);
    }

    /** CRM-14: Successful activity store creates it linked to deal */
    public function test_crm_activities_store_succeeds(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->postJson('/api/v1/crm/activities', [
            'type' => 'ligacao',
            'customer_id' => $this->customerA->id,
            'deal_id' => $deal->id,
            'title' => 'Ligação de follow-up',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('crm_activities', [
            'customer_id' => $this->customerA->id,
            'deal_id' => $deal->id,
            'type' => 'ligacao',
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    /** CRM-15: List activities filtered by deal_id returns correct count */
    public function test_crm_activities_list_filtered_by_deal(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $this->pipelineA->id,
            'stage_id' => $this->stageA->id,
        ]);

        CrmActivity::factory(3)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'deal_id' => $deal->id,
        ]);
        CrmActivity::factory(2)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'deal_id' => null,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson("/api/v1/crm/activities?deal_id={$deal->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    // ══════════════════════════════════════════════════════════════
    // ── CRM PIPELINES — CRM-16 a CRM-17
    // ══════════════════════════════════════════════════════════════

    /** CRM-16: Pipelines index returns only own tenant's pipelines */
    public function test_crm_pipelines_list_only_own_tenant(): void
    {
        CrmPipeline::factory(2)->create(['tenant_id' => $this->tenantA->id]);
        CrmPipeline::factory(3)->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        // $pipelineA (from setUp) + 2 above = 3 total for tenantA
        $response = $this->getJson('/api/v1/crm/pipelines')->assertOk();
        foreach ($response->json('data') as $pipeline) {
            $this->assertEquals($this->tenantA->id, $pipeline['tenant_id']);
        }
    }

    /** CRM-17: Store pipeline without required fields must fail */
    public function test_crm_pipelines_store_requires_name_and_stages(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/crm/pipelines', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'stages']);
    }

    // ══════════════════════════════════════════════════════════════
    // ── CUSTOMER MERGE — CM-01 a CM-05
    // ══════════════════════════════════════════════════════════════

    /** CM-01: Search duplicates by name returns 200 */
    public function test_search_duplicates_by_name(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Empresa Duplicada']);
        Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Empresa Duplicada']);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson('/api/v1/customers/search-duplicates?type=name')
            ->assertOk();
    }

    /** CM-02: Search duplicates by email returns 200 */
    public function test_search_duplicates_by_email(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'email' => 'dup@test.com']);
        Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'email' => 'dup@test.com']);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->getJson('/api/v1/customers/search-duplicates?type=email')
            ->assertOk();
    }

    /** CM-03: Merge with non-existent IDs must return 422 */
    public function test_customer_merge_requires_valid_ids(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/customers/merge', [
            'primary_id' => 999999,
            'duplicate_ids' => [888888],
        ])->assertUnprocessable();
    }

    /** CM-04: Successful merge soft-deletes the duplicate */
    public function test_customer_merge_succeeds(): void
    {
        $primary = Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Primary Customer']);
        $duplicate = Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Duplicate Customer']);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/customers/merge', [
            'primary_id' => $primary->id,
            'duplicate_ids' => [$duplicate->id],
        ])->assertOk();

        $this->assertSoftDeleted('customers', ['id' => $duplicate->id]);
    }

    /** CM-05: Merge must reject IDs from another tenant (422) */
    public function test_customer_merge_cannot_use_other_tenant_ids(): void
    {
        $primaryA = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/customers/merge', [
            'primary_id' => $primaryA->id,
            'duplicate_ids' => [$customerB->id],
        ])->assertUnprocessable();
    }
}
