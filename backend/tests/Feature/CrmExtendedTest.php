<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * CRM Extended Tests — validates deal lifecycle, pipeline management,
 * activities, customer 360, and stage transitions.
 */
class CrmExtendedTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        // Ensure necessary permissions exist for tests bypassing CheckPermission middleware
        Permission::findOrCreate('platform.dashboard.view', 'web');
        Permission::findOrCreate('finance.receivable.view', 'web');
        Permission::findOrCreate('fiscal.note.view', 'web');
        Permission::findOrCreate('customer.document.view', 'web');

        Sanctum::actingAs($this->user, ['*']);
    }

    // ── DASHBOARD ──

    public function test_crm_dashboard_returns_metrics(): void
    {
        $response = $this->getJson('/api/v1/crm/dashboard');
        $response->assertOk();
    }

    public function test_crm_constants_returns_enums(): void
    {
        $response = $this->getJson('/api/v1/crm/constants');
        $response->assertOk();
    }

    // ── DEALS ──

    public function test_deals_index_returns_paginated_list(): void
    {
        $response = $this->getJson('/api/v1/crm/deals');
        $response->assertOk();
    }

    public function test_create_deal_with_valid_data(): void
    {
        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage = CrmPipelineStage::factory()->create([
            'pipeline_id' => $pipeline->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/crm/deals', [
            'title' => 'Negócio com Cliente X',
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'value' => 50000.00,
        ]);

        $response->assertCreated();
    }

    public function test_deal_mark_won(): void
    {
        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage = CrmPipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'tenant_id' => $this->tenant->id]);

        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
        ]);

        $response = $this->putJson("/api/v1/crm/deals/{$deal->id}/won");

        $response->assertOk();

        $deal->refresh();
        $this->assertEquals('won', $deal->status);
    }

    public function test_deal_mark_lost(): void
    {
        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage = CrmPipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'tenant_id' => $this->tenant->id]);

        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
        ]);

        $response = $this->putJson("/api/v1/crm/deals/{$deal->id}/lost", [
            'loss_reason' => 'Cliente escolheu concorrente',
        ]);

        $response->assertOk();

        $deal->refresh();
        $this->assertEquals('lost', $deal->status);
    }

    // ── ACTIVITIES ──

    public function test_activities_index_returns_list(): void
    {
        $response = $this->getJson('/api/v1/crm/activities');
        $response->assertOk();
    }

    public function test_create_activity(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson('/api/v1/crm/activities', [
            'deal_id' => $deal->id,
            'customer_id' => $this->customer->id,
            'type' => 'ligacao',
            'title' => 'Ligação de follow-up',
            'scheduled_at' => now()->addDays(1)->toISOString(),
        ]);

        $response->assertCreated();
    }

    public function test_update_activity_updates_fields(): void
    {
        $activity = CrmActivity::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'title' => 'Contato inicial',
            'type' => 'ligacao',
        ]);

        $response = $this->putJson("/api/v1/crm/activities/{$activity->id}", [
            'title' => 'Contato atualizado',
            'completed_at' => now()->toISOString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Contato atualizado');
    }

    public function test_other_tenant_activity_is_not_accessible_for_update(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $activity = CrmActivity::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->putJson("/api/v1/crm/activities/{$activity->id}", [
            'title' => 'Nao deveria atualizar',
        ]);

        $response->assertNotFound();
    }

    // ── PIPELINES ──

    public function test_pipelines_index_returns_list(): void
    {
        $response = $this->getJson('/api/v1/crm/pipelines');
        $response->assertOk();
    }

    public function test_create_pipeline(): void
    {
        $response = $this->postJson('/api/v1/crm/pipelines', [
            'name' => 'Pipeline de Vendas',
            'slug' => 'pipeline-de-vendas',
            'stages' => [
                ['name' => 'Nova', 'color' => '#ffffff', 'order_index' => 1],
            ],
        ]);

        $response->assertCreated();
    }

    // ── CUSTOMER 360 ──

    public function test_customer_360_returns_aggregated_data(): void
    {
        CrmActivity::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'title' => 'Contato de auditoria',
            'type' => 'ligacao',
            'channel' => 'phone',
        ]);

        $response = $this->getJson("/api/v1/crm/customers/{$this->customer->id}/360");

        $response->assertOk()
            ->assertJsonPath('data.timeline.0.title', 'Contato de auditoria')
            ->assertJsonPath('data.timeline.0.type', 'ligacao')
            ->assertJsonPath('data.timeline.0.user.id', $this->user->id)
            ->assertJsonPath('data.documents', []);
    }

    public function test_customer_360_returns_documents_only_with_document_permission(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        EquipmentDocument::query()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment->id,
            'type' => 'certificate',
            'name' => 'Certificado A1',
            'file_path' => 'equipment-documents/teste.pdf',
            'uploaded_by' => $this->user->id,
        ]);

        $withoutPermission = $this->getJson("/api/v1/crm/customers/{$this->customer->id}/360");
        $withoutPermission->assertOk()
            ->assertJsonCount(0, 'data.documents');

        $this->user->givePermissionTo('customer.document.view');

        $withPermission = $this->getJson("/api/v1/crm/customers/{$this->customer->id}/360");
        $withPermission->assertOk()
            ->assertJsonCount(1, 'data.documents')
            ->assertJsonPath('data.documents.0.name', 'Certificado A1')
            ->assertJsonPath('data.documents.0.equipment.id', $equipment->id);
    }

    public function test_customer_360_restricts_quotes_by_seller_and_counts_invoiced_metrics(): void
    {
        $otherSeller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
        ]);

        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'assigned_to' => $this->user->id,
            'title' => 'Deal do vendedor atual',
        ]);

        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'assigned_to' => $otherSeller->id,
            'title' => 'Deal de outro vendedor',
        ]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'quote_number' => 'Q-SELLER-001',
            'status' => Quote::STATUS_INVOICED,
            'total' => 1250.50,
            'approved_at' => now()->subDay(),
        ]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $otherSeller->id,
            'quote_number' => 'Q-OTHER-001',
            'status' => Quote::STATUS_APPROVED,
            'total' => 999.99,
            'approved_at' => now()->subDays(2),
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_INVOICED,
            'total' => 700.25,
        ]);

        $response = $this->getJson("/api/v1/crm/customers/{$this->customer->id}/360");

        $response->assertOk()
            ->assertJsonCount(1, 'data.deals')
            ->assertJsonPath('data.deals.0.title', 'Deal do vendedor atual')
            ->assertJsonCount(1, 'data.quotes')
            ->assertJsonPath('data.quotes.0.quote_number', 'Q-SELLER-001')
            ->assertJsonPath('data.quotes.0.status', Quote::STATUS_INVOICED)
            ->assertJsonPath('data.health_breakdown.orcamento_aprovado.score', 15)
            ->assertJsonPath('data.metrics.conversion_rate', 100)
            ->assertJsonPath('data.metrics.ltv', '1950.75')
            ->assertJsonPath('data.metrics.benchmarking.0.value', '700.25');
    }
}
