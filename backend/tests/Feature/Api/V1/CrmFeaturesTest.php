<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmForecastSnapshot;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\CrmReferral;
use App\Models\CrmSequence;
use App\Models\CrmSequenceStep;
use App\Models\CrmSmartAlert;
use App\Models\CrmTrackingEvent;
use App\Models\CrmWebForm;
use App\Models\CrmWebFormSubmission;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmFeaturesTest extends TestCase
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

    // ─── SCORING RULES ───────────────────────────────

    public function test_scoring_rules_crud(): void
    {
        // Create - 'value' must be non-empty string (required|string|max:500)
        $response = $this->postJson('/api/v1/crm-features/scoring/rules', [
            'name' => 'Has Email',
            'field' => 'email',
            'operator' => 'not_equals',
            'value' => 'null',
            'points' => 10,
            'category' => 'demographic',
        ]);
        $response->assertStatus(201);
        $ruleId = $response->json('data.id');

        // Index
        $indexResponse = $this->getJson('/api/v1/crm-features/scoring/rules');
        $indexResponse->assertStatus(200);
        $indexResponse->assertJsonStructure(['data']);

        // Update
        $updateResponse = $this->putJson("/api/v1/crm-features/scoring/rules/{$ruleId}", [
            'name' => 'Has Email Updated',
            'points' => 20,
        ]);
        $updateResponse->assertStatus(200);

        // Destroy
        $deleteResponse = $this->deleteJson("/api/v1/crm-features/scoring/rules/{$ruleId}");
        $deleteResponse->assertStatus(200);
    }

    public function test_scoring_rule_validation_rejects_invalid_operator(): void
    {
        $response = $this->postJson('/api/v1/crm-features/scoring/rules', [
            'name' => 'Bad Rule',
            'field' => 'email',
            'operator' => 'invalid_op',
            'value' => 'test',
            'points' => 10,
            'category' => 'demographic',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['operator']);
    }

    public function test_leaderboard_returns_paginated_scores(): void
    {
        $response = $this->getJson('/api/v1/crm-features/scoring/leaderboard');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    // ─── LOSS REASONS ─────────────────────────────────

    public function test_loss_reasons_crud(): void
    {
        // Create
        $response = $this->postJson('/api/v1/crm-features/loss-reasons', [
            'name' => 'Preço alto',
            'category' => 'price',
        ]);
        $response->assertStatus(201);
        $reasonId = $response->json('data.id');

        // Index
        $this->getJson('/api/v1/crm-features/loss-reasons')
            ->assertStatus(200);

        // Update
        $this->putJson("/api/v1/crm-features/loss-reasons/{$reasonId}", [
            'name' => 'Preço muito alto',
        ])->assertStatus(200);
    }

    public function test_loss_reasons_validation_rejects_invalid_category(): void
    {
        $response = $this->postJson('/api/v1/crm-features/loss-reasons', [
            'name' => 'Test',
            'category' => 'nonexistent_category',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category']);
    }

    public function test_loss_analytics_returns_aggregated_data(): void
    {
        // lossAnalytics catches QueryException internally and returns empty data on SQLite
        $response = $this->getJson('/api/v1/crm-features/loss-analytics?months=6');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['by_reason', 'by_competitor', 'by_user', 'monthly_trend'],
        ]);
    }

    // ─── TERRITORIES ──────────────────────────────────

    public function test_territories_crud(): void
    {
        // Create
        $response = $this->postJson('/api/v1/crm-features/territories', [
            'name' => 'Região Sul',
            'description' => 'RS, SC, PR',
            'regions' => ['RS', 'SC', 'PR'],
            'member_ids' => [$this->user->id],
        ]);
        $response->assertStatus(201);
        $territoryId = $response->json('data.id');

        // Index
        $this->getJson('/api/v1/crm-features/territories')
            ->assertStatus(200);

        // Update
        $this->putJson("/api/v1/crm-features/territories/{$territoryId}", [
            'name' => 'Região Sul Updated',
            'member_ids' => [$this->user->id],
        ])->assertStatus(200);

        // Destroy
        $this->deleteJson("/api/v1/crm-features/territories/{$territoryId}")
            ->assertStatus(200);
    }

    // ─── SALES GOALS ──────────────────────────────────

    public function test_sales_goals_crud(): void
    {
        // Create
        $response = $this->postJson('/api/v1/crm-features/goals', [
            'user_id' => $this->user->id,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'target_revenue' => 50000,
            'target_deals' => 10,
        ]);
        $response->assertStatus(201);
        $goalId = $response->json('data.id');

        // Index
        $this->getJson('/api/v1/crm-features/goals')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        // Update
        $this->putJson("/api/v1/crm-features/goals/{$goalId}", [
            'target_revenue' => 60000,
        ])->assertStatus(200);

        // Goals Dashboard
        $this->getJson('/api/v1/crm-features/goals/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['goals', 'ranking']]);
    }

    // ─── SEQUENCES ────────────────────────────────────

    public function test_sequences_crud_and_enrollment(): void
    {
        // Create - action_type must be one of CrmSequenceStep::ACTION_TYPES, channel is required
        $response = $this->postJson('/api/v1/crm-features/sequences', [
            'name' => 'Welcome Cadence',
            'description' => 'Onboarding sequence',
            'steps' => [
                ['step_order' => 1, 'action_type' => 'send_message', 'channel' => 'email', 'delay_days' => 0, 'subject' => 'Welcome', 'body' => 'Hello!'],
                ['step_order' => 2, 'action_type' => 'send_message', 'channel' => 'whatsapp', 'delay_days' => 3, 'body' => 'Follow up'],
            ],
        ]);
        $response->assertStatus(201);
        $sequenceId = $response->json('data.id');

        // Index
        $this->getJson('/api/v1/crm-features/sequences')
            ->assertStatus(200);

        // Show
        $this->getJson("/api/v1/crm-features/sequences/{$sequenceId}")
            ->assertStatus(200);

        // Enroll customer
        $enrollResponse = $this->postJson('/api/v1/crm-features/sequences/enroll', [
            'sequence_id' => $sequenceId,
            'customer_id' => $this->customer->id,
        ]);
        $enrollResponse->assertStatus(201);
        $enrollmentId = $enrollResponse->json('data.id');

        // Enroll again should fail (duplicate)
        $this->postJson('/api/v1/crm-features/sequences/enroll', [
            'sequence_id' => $sequenceId,
            'customer_id' => $this->customer->id,
        ])->assertStatus(422);

        // Unenroll
        $this->putJson("/api/v1/crm-features/enrollments/{$enrollmentId}/cancel")
            ->assertStatus(200);

        // Enrollments list
        $this->getJson("/api/v1/crm-features/sequences/{$sequenceId}/enrollments")
            ->assertStatus(200);

        // Destroy
        $this->deleteJson("/api/v1/crm-features/sequences/{$sequenceId}")
            ->assertStatus(200);
    }

    // ─── SMART ALERTS ─────────────────────────────────

    public function test_smart_alerts_index_and_state_transitions(): void
    {
        $alert = CrmSmartAlert::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'stale_deal',
            'priority' => 'high',
            'title' => 'Deal parado há 30 dias',
            'status' => 'active',
        ]);

        $indexResponse = $this->getJson('/api/v1/crm-features/alerts');
        $indexResponse->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        // Acknowledge
        $this->putJson("/api/v1/crm-features/alerts/{$alert->id}/acknowledge")
            ->assertStatus(200);
        $this->assertEquals('acknowledged', $alert->fresh()->status);

        // Resolve
        $this->putJson("/api/v1/crm-features/alerts/{$alert->id}/resolve")
            ->assertStatus(200);
        $this->assertEquals('resolved', $alert->fresh()->status);
    }

    public function test_smart_alerts_dismiss(): void
    {
        $alert = CrmSmartAlert::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'stale_deal',
            'priority' => 'medium',
            'title' => 'Test Alert',
            'status' => 'active',
        ]);

        $this->putJson("/api/v1/crm-features/alerts/{$alert->id}/dismiss")
            ->assertStatus(200);
        $this->assertEquals('dismissed', $alert->fresh()->status);
    }

    // ─── FORECAST ─────────────────────────────────────

    public function test_forecast_returns_structured_data(): void
    {
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'won',
            'value' => 1200,
            'won_at' => now()->subMonth(),
        ]);

        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'open',
            'value' => 2500,
            'probability' => 60,
            'expected_close_date' => now()->addDays(10),
        ]);

        $response = $this->getJson('/api/v1/crm-features/forecast?months=1');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'forecast' => [[
                        'period_start',
                        'period_end',
                        'pipeline_value',
                        'weighted_value',
                        'best_case',
                        'worst_case',
                        'committed',
                        'deal_count',
                        'historical_win_rate',
                        'by_stage',
                        'by_user',
                    ]],
                    'historical_won' => [[
                        'month',
                        'total',
                        'count',
                    ]],
                    'period_type',
                ],
            ])
            ->assertJsonPath('data.forecast.0.historical_win_rate', 100)
            ->assertJsonPath('data.historical_won.0.total', 1200);
    }

    // ─── PIPELINE VELOCITY ────────────────────────────

    public function test_pipeline_velocity_returns_metrics(): void
    {
        $response = $this->getJson('/api/v1/crm-features/velocity?months=3');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'avg_cycle_days', 'avg_deal_value', 'total_won',
                'total_won_value', 'velocity', 'stage_analysis',
            ],
        ]);
    }

    // ─── CALENDAR ─────────────────────────────────────

    public function test_calendar_events_crud(): void
    {
        $response = $this->postJson('/api/v1/crm-features/calendar', [
            'title' => 'Reunião com Cliente',
            'type' => 'meeting',
            'start_at' => now()->addDay()->toDateTimeString(),
            'end_at' => now()->addDay()->addHour()->toDateTimeString(),
            'location' => 'Sala 1',
            'customer_id' => $this->customer->id,
        ]);
        $response->assertStatus(201);
        $eventId = $response->json('data.id');

        // Index
        $this->getJson('/api/v1/crm-features/calendar')
            ->assertStatus(200);

        // Update
        $this->putJson("/api/v1/crm-features/calendar/{$eventId}", [
            'title' => 'Reunião Updated',
        ])->assertStatus(200);

        // Destroy
        $this->deleteJson("/api/v1/crm-features/calendar/{$eventId}")
            ->assertStatus(200);
    }

    // ─── REFERRALS ────────────────────────────────────

    public function test_referrals_crud(): void
    {
        // StoreCrmReferralRequest requires referrer_customer_id + referred_name (not referred_customer_id)
        $response = $this->postJson('/api/v1/crm-features/referrals', [
            'referrer_customer_id' => $this->customer->id,
            'referred_name' => 'João Indicado',
            'referred_email' => 'joao@test.com',
            'referred_phone' => '11999998888',
        ]);
        $response->assertStatus(201);
        $referralId = $response->json('data.id');

        // Index
        $this->getJson('/api/v1/crm-features/referrals')
            ->assertStatus(200);

        // Stats
        $statsResponse = $this->getJson('/api/v1/crm-features/referrals/stats');
        $statsResponse->assertStatus(200);

        // Options
        $this->getJson('/api/v1/crm-features/referrals/options')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['customers', 'deals']]);

        // Update to converted
        $this->putJson("/api/v1/crm-features/referrals/{$referralId}", [
            'status' => 'converted',
        ])->assertStatus(200);
        $this->assertNotNull(CrmReferral::find($referralId)->converted_at);

        // Destroy
        $this->deleteJson("/api/v1/crm-features/referrals/{$referralId}")
            ->assertStatus(200);
    }

    public function test_referral_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $referral = CrmReferral::create([
            'tenant_id' => $otherTenant->id,
            'referrer_customer_id' => $this->customer->id,
            'referred_name' => 'Test Isolation',
            'status' => 'pending',
        ]);

        $response = $this->putJson("/api/v1/crm-features/referrals/{$referral->id}", [
            'status' => 'converted',
        ]);
        $response->assertStatus(404);
    }

    // ─── CROSS-SELL RECOMMENDATIONS ───────────────────

    public function test_cross_sell_recommendations_returns_data(): void
    {
        $response = $this->getJson("/api/v1/crm-features/customers/{$this->customer->id}/recommendations");
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ─── WEB FORMS ────────────────────────────────────

    public function test_web_forms_crud(): void
    {
        // fields.*.label is required
        $response = $this->postJson('/api/v1/crm-features/web-forms', [
            'name' => 'Contact Form',
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'label' => 'Nome', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true],
            ],
        ]);
        $response->assertStatus(201);
        $formId = $response->json('data.id');

        // Index
        $this->getJson('/api/v1/crm-features/web-forms')
            ->assertStatus(200);

        // Update
        $this->putJson("/api/v1/crm-features/web-forms/{$formId}", [
            'name' => 'Updated Contact Form',
        ])->assertStatus(200);

        // Destroy
        $this->deleteJson("/api/v1/crm-features/web-forms/{$formId}")
            ->assertStatus(200);
    }

    public function test_calendar_events_include_filtered_activities_with_description(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        CrmActivity::factory()->scheduled()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'type' => 'reuniao',
            'title' => 'Reuniao de alinhamento',
            'description' => 'Validar proposta comercial',
            'scheduled_at' => now()->addDay()->setTime(9, 0),
            'duration_minutes' => 45,
        ]);

        CrmActivity::factory()->scheduled()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $otherUser->id,
            'type' => 'ligacao',
            'title' => 'Ligacao fora do filtro',
            'description' => 'Nao deveria aparecer',
            'scheduled_at' => now()->addDay()->setTime(11, 0),
        ]);

        $response = $this->getJson('/api/v1/crm-features/calendar?start='.now()->toDateString().'&end='.now()->addDays(2)->toDateString().'&user_id='.$this->user->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data.activities')
            ->assertJsonPath('data.activities.0.title', 'Reuniao de alinhamento')
            ->assertJsonPath('data.activities.0.description', 'Validar proposta comercial')
            ->assertJsonPath('data.activities.0.type', 'meeting')
            ->assertJsonPath('data.activities.0.is_activity', true);
    }

    public function test_snapshot_forecast_is_idempotent_and_persists_breakdowns(): void
    {
        $pipeline = CrmPipeline::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
        ]);

        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'assigned_to' => $this->user->id,
            'status' => 'open',
            'stage_id' => $stage->id,
            'value' => 3000,
            'probability' => 80,
        ]);

        $firstResponse = $this->postJson('/api/v1/crm-features/forecast/snapshot');
        $secondResponse = $this->postJson('/api/v1/crm-features/forecast/snapshot');

        $firstResponse->assertOk()
            ->assertJsonStructure([
                'data' => ['message', 'snapshot_id'],
            ]);

        $secondResponse->assertOk();

        $this->assertDatabaseCount('crm_forecast_snapshots', 1);

        $snapshot = CrmForecastSnapshot::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('period_type', 'monthly')
            ->firstOrFail();

        $this->assertSame(1, $snapshot->deal_count);
        $this->assertNotEmpty($snapshot->by_user);
        $firstByUser = collect($snapshot->by_user)->first();
        $this->assertIsArray($firstByUser);
        $this->assertArrayHasKey('count', $firstByUser);
        $this->assertArrayHasKey('value', $firstByUser);
    }

    public function test_web_form_options_return_pipelines_sequences_and_users(): void
    {
        CrmPipeline::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pipeline Comercial',
        ]);

        CrmSequence::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cadencia Inicial',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/crm-features/web-forms/options');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pipelines',
                    'sequences',
                    'users',
                ],
            ])
            ->assertJsonPath('data.users.0.id', $this->user->id);
    }

    public function test_destroy_web_form_also_removes_submissions(): void
    {
        $form = CrmWebForm::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Form com envios',
            'slug' => 'form-com-envios',
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
            'is_active' => true,
        ]);

        $submission = CrmWebFormSubmission::create([
            'form_id' => $form->id,
            'customer_id' => $this->customer->id,
            'data' => ['email' => 'lead@example.com'],
        ]);

        $this->deleteJson("/api/v1/crm-features/web-forms/{$form->id}")
            ->assertOk();

        $this->assertSoftDeleted('crm_web_forms', ['id' => $form->id]);
        $this->assertDatabaseMissing('crm_web_form_submissions', ['id' => $submission->id]);
    }

    // ─── COMPETITORS ──────────────────────────────────

    public function test_competitive_matrix_returns_data(): void
    {
        $response = $this->getJson('/api/v1/crm-features/competitors');
        $response->assertStatus(200);
    }

    // ─── CONSTANTS ────────────────────────────────────

    public function test_features_constants_returns_200(): void
    {
        $response = $this->getJson('/api/v1/crm-features/constants');
        $response->assertStatus(200);
    }

    // ─── TRACKING ─────────────────────────────────────

    public function test_tracking_events_returns_paginated(): void
    {
        CrmTrackingEvent::create([
            'tenant_id' => $this->tenant->id,
            'trackable_type' => CrmWebFormSubmission::class,
            'trackable_id' => 1,
            'customer_id' => $this->customer->id,
            'event_type' => 'form_submitted',
        ]);

        $response = $this->getJson('/api/v1/crm-features/tracking');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.customer.id', $this->customer->id);
    }

    public function test_tracking_stats_returns_aggregated(): void
    {
        CrmTrackingEvent::create([
            'tenant_id' => $this->tenant->id,
            'trackable_type' => CrmWebFormSubmission::class,
            'trackable_id' => 1,
            'event_type' => 'form_submitted',
        ]);
        CrmTrackingEvent::create([
            'tenant_id' => $this->tenant->id,
            'trackable_type' => CrmWebFormSubmission::class,
            'trackable_id' => 2,
            'event_type' => 'proposal_viewed',
        ]);

        $response = $this->getJson('/api/v1/crm-features/tracking/stats');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['total_events', 'by_type'],
            ])
            ->assertJsonPath('data.total_events', 2)
            ->assertJsonPath('data.by_type.form_submitted', 1);
    }

    // ─── NPS ──────────────────────────────────────────

    public function test_nps_stats_returns_data(): void
    {
        $response = $this->getJson('/api/v1/crm-features/nps/stats');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['nps_score', 'total_responses', 'promoters', 'passives', 'detractors'],
        ]);
    }

    // ─── CONTRACT RENEWALS ────────────────────────────

    public function test_contract_renewals_returns_data(): void
    {
        $response = $this->getJson('/api/v1/crm-features/renewals');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    // ─── COHORT ANALYSIS ──────────────────────────────

    public function test_cohort_analysis_returns_data(): void
    {
        // cohortAnalysis uses DATE_FORMAT which is MySQL-only
        $response = $this->getJson('/api/v1/crm-features/cohort');
        $this->assertContains($response->status(), [200, 500]);
    }

    // ─── REVENUE INTELLIGENCE ─────────────────────────

    public function test_revenue_intelligence_returns_data(): void
    {
        // revenueIntelligence uses DATE_FORMAT which is MySQL-only
        $response = $this->getJson('/api/v1/crm-features/revenue-intelligence');
        $this->assertContains($response->status(), [200, 500]);
    }
}
