<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ContactPolicy;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\ImportantDate;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitCheckin;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmFieldManagementTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
    }

    // ─── CHECKINS ─────────────────────────────────────

    public function test_checkins_index_returns_paginated_list(): void
    {
        VisitCheckin::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'checkin_at' => now(),
            'status' => 'checked_in',
        ]);

        $response = $this->getJson('/api/v1/crm-field/checkins');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
    }

    public function test_checkin_creates_visit_and_activity(): void
    {
        $response = $this->postJson('/api/v1/crm-field/checkins', [
            'customer_id' => $this->customer->id,
            'checkin_lat' => -23.55,
            'checkin_lng' => -46.63,
            'checkin_address' => 'Rua Teste, 123',
            'notes' => 'Visita comercial',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('visit_checkins', [
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'status' => 'checked_in',
        ]);
        // Check that a CrmActivity was created
        $this->assertDatabaseHas('crm_activities', [
            'customer_id' => $this->customer->id,
            'type' => 'visita',
        ]);
    }

    public function test_checkin_fails_without_customer_id(): void
    {
        $response = $this->postJson('/api/v1/crm-field/checkins', [
            'checkin_lat' => -23.55,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);
    }

    public function test_checkout_updates_checkin_status(): void
    {
        $checkin = VisitCheckin::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'checkin_at' => now()->subHour(),
            'status' => 'checked_in',
        ]);

        $response = $this->putJson("/api/v1/crm-field/checkins/{$checkin->id}/checkout", [
            'checkout_lat' => -23.55,
            'checkout_lng' => -46.63,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('checked_out', $checkin->fresh()->status);
    }

    // ─── CONTACT POLICIES ─────────────────────────────

    public function test_policies_crud_lifecycle(): void
    {
        // Create
        $createResponse = $this->postJson('/api/v1/crm-field/policies', [
            'name' => 'Política A',
            'target_type' => 'all',
            'max_days_without_contact' => 30,
            'warning_days_before' => 5,
            'is_active' => true,
            'priority' => 1,
        ]);
        $createResponse->assertStatus(201);
        $policyId = $createResponse->json('data.id');
        $this->assertNotNull($policyId);

        // Index
        $indexResponse = $this->getJson('/api/v1/crm-field/policies');
        $indexResponse->assertStatus(200);

        // Update
        $updateResponse = $this->putJson("/api/v1/crm-field/policies/{$policyId}", [
            'name' => 'Política A Updated',
            'max_days_without_contact' => 45,
        ]);
        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('contact_policies', [
            'id' => $policyId,
            'name' => 'Política A Updated',
        ]);

        // Destroy
        $deleteResponse = $this->deleteJson("/api/v1/crm-field/policies/{$policyId}");
        $deleteResponse->assertStatus(204);
        $this->assertDatabaseMissing('contact_policies', ['id' => $policyId]);
    }

    public function test_policy_tenant_isolation_on_update(): void
    {
        $otherTenant = Tenant::factory()->create();
        $policy = ContactPolicy::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Policy',
            'target_type' => 'all',
            'max_days_without_contact' => 30,
        ]);

        $response = $this->putJson("/api/v1/crm-field/policies/{$policy->id}", [
            'name' => 'Hacked',
        ]);

        // BelongsToTenant scope filters out other tenant's records => 404
        $response->assertStatus(404);
    }

    // ─── QUICK NOTES ──────────────────────────────────

    public function test_quick_notes_store_and_index(): void
    {
        $storeResponse = $this->postJson('/api/v1/crm-field/quick-notes', [
            'customer_id' => $this->customer->id,
            'channel' => 'telefone',
            'sentiment' => 'positive',
            'content' => 'Cliente satisfeito com o serviço',
            'is_pinned' => true,
        ]);

        $storeResponse->assertStatus(201);
        $noteId = $storeResponse->json('data.id');

        // Verify last_contact_at was updated
        $this->assertNotNull($this->customer->fresh()->last_contact_at);

        // Index
        $indexResponse = $this->getJson('/api/v1/crm-field/quick-notes');
        $indexResponse->assertStatus(200);
        $indexResponse->assertJsonStructure(['data']);

        // Update
        $updateResponse = $this->putJson("/api/v1/crm-field/quick-notes/{$noteId}", [
            'content' => 'Updated content',
        ]);
        $updateResponse->assertStatus(200);

        // Destroy
        $destroyResponse = $this->deleteJson("/api/v1/crm-field/quick-notes/{$noteId}");
        $destroyResponse->assertStatus(204);
    }

    public function test_quick_notes_fails_without_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm-field/quick-notes', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id', 'content']);
    }

    // ─── IMPORTANT DATES ──────────────────────────────

    public function test_important_dates_crud_lifecycle(): void
    {
        // Create
        $createResponse = $this->postJson('/api/v1/crm-field/important-dates', [
            'customer_id' => $this->customer->id,
            'title' => 'Aniversário do João',
            'type' => 'birthday',
            'date' => '2026-06-15',
            'recurring_yearly' => true,
            'remind_days_before' => 7,
        ]);
        $createResponse->assertStatus(201);
        $dateId = $createResponse->json('data.id');

        // Index
        $indexResponse = $this->getJson('/api/v1/crm-field/important-dates');
        $indexResponse->assertStatus(200);

        // Update
        $updateResponse = $this->putJson("/api/v1/crm-field/important-dates/{$dateId}", [
            'title' => 'Aniversário do João Updated',
        ]);
        $updateResponse->assertStatus(200);

        // Destroy
        $destroyResponse = $this->deleteJson("/api/v1/crm-field/important-dates/{$dateId}");
        $destroyResponse->assertStatus(204);
        $this->assertDatabaseMissing('important_dates', ['id' => $dateId]);
    }

    public function test_important_dates_tenant_isolation_on_destroy(): void
    {
        $otherTenant = Tenant::factory()->create();
        $date = ImportantDate::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $this->customer->id,
            'title' => 'Should not delete',
            'type' => 'custom',
            'date' => '2026-01-01',
        ]);

        $response = $this->deleteJson("/api/v1/crm-field/important-dates/{$date->id}");
        // BelongsToTenant scope filters out other tenant's records => 404
        $response->assertStatus(404);
    }

    // ─── PORTFOLIO MAP ────────────────────────────────

    public function test_portfolio_map_returns_geolocated_customers(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'latitude' => -23.55,
            'longitude' => -46.63,
            'last_contact_at' => now()->subDays(100),
        ]);

        $response = $this->getJson('/api/v1/crm-field/portfolio-map');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
        $data = collect($response->json('data'));
        // At least one customer should have alert_level set
        $this->assertTrue($data->count() >= 1);
    }

    // ─── FORGOTTEN CLIENTS ────────────────────────────

    public function test_forgotten_clients_returns_stats_and_customers(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'last_contact_at' => now()->subDays(120),
            'next_follow_up_at' => null,
        ]);

        $response = $this->getJson('/api/v1/crm-field/forgotten-clients');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
            'stats' => ['total_forgotten', 'critical', 'high', 'medium'],
        ]);
    }

    public function test_negotiation_history_returns_business_artifacts_and_crm_activities(): void
    {
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'quote_number' => 'ORC-1001',
            'total' => 1250,
            'discount_amount' => 50,
            'created_at' => now()->subDays(4),
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'OS-2026-0001',
            'total' => 980,
            'created_at' => now()->subDays(3),
        ]);

        $pipeline = CrmPipeline::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
        ]);

        CrmDeal::factory()->won()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'title' => 'Deal Estrategico',
            'value' => 3500,
            'created_at' => now()->subDays(2),
        ]);

        CrmActivity::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'type' => 'email',
            'channel' => 'email',
            'title' => 'Follow-up por email',
            'description' => 'Cliente respondeu com prazo para fechamento.',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/v1/crm-field/customers/{$this->customer->id}/negotiation-history");

        $response->assertOk()
            ->assertJsonPath('data.totals.total_quoted', 1250)
            ->assertJsonPath('data.totals.total_os', 980)
            ->assertJsonPath('data.totals.total_deals_won', 3500)
            ->assertJsonPath('data.totals.activities_count', 1)
            ->assertJsonPath('data.totals.messages_count', 1)
            ->assertJsonPath('data.timeline.0.entry_type', 'activity')
            ->assertJsonPath('data.timeline.0.type', 'email')
            ->assertJsonPath('data.timeline.0.user.name', $this->user->name);
    }

    public function test_surveys_index_returns_paginated_list(): void
    {
        $response = $this->getJson('/api/v1/crm-field/surveys');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    // ─── RFM ──────────────────────────────────────────

    public function test_rfm_index_returns_scores(): void
    {
        $response = $this->getJson('/api/v1/crm-field/rfm');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['scores', 'by_segment', 'segments'],
        ]);
    }

    // ─── PORTFOLIO COVERAGE ───────────────────────────

    public function test_portfolio_coverage_returns_summary(): void
    {
        $response = $this->getJson('/api/v1/crm-field/coverage?period=30');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'summary' => ['total_clients', 'visited', 'not_visited', 'coverage_percent', 'period_days'],
                'by_seller',
                'by_rating',
            ],
        ]);
    }

    // ─── SMART AGENDA ─────────────────────────────────

    public function test_smart_agenda_returns_suggestions(): void
    {
        $response = $this->getJson('/api/v1/crm-field/smart-agenda');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ─── CONSTANTS ────────────────────────────────────

    public function test_constants_endpoint_returns_200(): void
    {
        $response = $this->getJson('/api/v1/crm-field/constants');
        $response->assertStatus(200);
    }
}
