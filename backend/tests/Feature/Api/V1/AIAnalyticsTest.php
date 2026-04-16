<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ServiceCallStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AIAnalyticsTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── predictive-maintenance ──────────────────────────────────

    public function test_predictive_maintenance_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/predictive-maintenance');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_predictive_maintenance_returns_expected_structure(): void
    {
        Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/ai/predictive-maintenance');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('predictions', $data);
        $this->assertArrayHasKey('total_analyzed', $data);
        $this->assertArrayHasKey('critical_count', $data);
        $this->assertArrayHasKey('high_count', $data);
    }

    // ── expense-ocr-analysis ────────────────────────────────────

    public function test_expense_ocr_analysis_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/expense-ocr-analysis');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('total_expenses', $data);
        $this->assertArrayHasKey('potential_duplicates', $data);
        $this->assertArrayHasKey('duplicate_count', $data);
        $this->assertArrayHasKey('without_receipt_count', $data);
    }

    public function test_expense_ocr_analysis_detects_structure(): void
    {
        Expense::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/ai/expense-ocr-analysis');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsInt($data['total_expenses']);
        $this->assertIsArray($data['potential_duplicates']);
    }

    // ── triage-suggestions ──────────────────────────────────────

    public function test_triage_suggestions_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/triage-suggestions');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('total_calls_analyzed', $data);
        $this->assertArrayHasKey('type_patterns', $data);
        $this->assertArrayHasKey('suggestions', $data);
    }

    public function test_triage_suggestions_uses_service_call_observations_and_technician(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'technician_id' => $technician->id,
            'observations' => 'Cliente com equipamento parado e necessidade urgente de manutencao.',
            'status' => ServiceCallStatus::IN_PROGRESS->value,
        ]);

        $response = $this->getJson('/api/v1/ai/triage-suggestions');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(1, $data['total_calls_analyzed']);
        $this->assertArrayHasKey('urgencia', $data['type_patterns']);
        $this->assertArrayHasKey('manutencao', $data['type_patterns']);
        $this->assertNotEmpty($data['technician_frequency']);
    }

    // ── sentiment-analysis ──────────────────────────────────────

    public function test_sentiment_analysis_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/sentiment-analysis');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('nps_score', $data);
        $this->assertArrayHasKey('total_ratings', $data);
        $this->assertArrayHasKey('sentiment_label', $data);
    }

    // ── dynamic-pricing ─────────────────────────────────────────

    public function test_dynamic_pricing_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/dynamic-pricing');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('suggestions', $data);
        $this->assertArrayHasKey('total_items_analyzed', $data);
    }

    // ── financial-anomalies ─────────────────────────────────────

    public function test_financial_anomalies_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/financial-anomalies');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('anomalies', $data);
        $this->assertArrayHasKey('total_analyzed', $data);
    }

    // ── voice-commands ──────────────────────────────────────────

    public function test_voice_command_suggestions_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/voice-commands');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('context', $data);
        $this->assertArrayHasKey('suggested_commands', $data);
        $this->assertIsArray($data['suggested_commands']);
        $this->assertNotEmpty($data['suggested_commands']);
    }

    public function test_voice_command_suggestions_counts_active_service_calls_with_current_statuses(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => ServiceCallStatus::IN_PROGRESS->value,
        ]);

        $response = $this->getJson('/api/v1/ai/voice-commands');

        $response->assertOk()
            ->assertJsonPath('data.context.open_service_calls', 2);
    }

    // ── natural-language-report ──────────────────────────────────

    public function test_natural_language_report_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/natural-language-report');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('report_text', $data);
        $this->assertArrayHasKey('period', $data);
        $this->assertArrayHasKey('metrics', $data);
    }

    public function test_natural_language_report_accepts_period_param(): void
    {
        $response = $this->getJson('/api/v1/ai/natural-language-report?period=week');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('week', $data['period']);
    }

    // ── customer-clustering ─────────────────────────────────────

    public function test_customer_clustering_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/customer-clustering');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('clusters', $data);
        $this->assertArrayHasKey('total_customers', $data);
        $this->assertArrayHasKey('segment_summary', $data);
    }

    // ── demand-forecast ─────────────────────────────────────────

    public function test_demand_forecast_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/demand-forecast');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('historical', $data);
        $this->assertArrayHasKey('forecast', $data);
        $this->assertArrayHasKey('trend', $data);
    }

    // ── route-optimization ──────────────────────────────────────

    public function test_route_optimization_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/route-optimization');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('optimized_order', $data);
        $this->assertArrayHasKey('total_pending', $data);
    }

    // ── smart-ticket-labeling ───────────────────────────────────

    public function test_smart_ticket_labeling_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/smart-ticket-labeling');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('labeled_tickets', $data);
        $this->assertArrayHasKey('total_analyzed', $data);
        $this->assertArrayHasKey('tag_distribution', $data);
    }

    public function test_smart_ticket_labeling_uses_observations_instead_of_legacy_fields(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'observations' => 'Solicitacao urgente para calibracao com certificado e laudo.',
        ]);

        $response = $this->getJson('/api/v1/ai/smart-ticket-labeling');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(1, $data['labeled_count']);
        $this->assertNotEmpty($data['labeled_tickets']);
        $this->assertStringContainsString($call->call_number, $data['labeled_tickets'][0]['title']);
        $this->assertContains('urgente', $data['labeled_tickets'][0]['suggested_tags']);
        $this->assertContains('certificado', $data['labeled_tickets'][0]['suggested_tags']);
    }

    // ── churn-prediction ────────────────────────────────────────

    public function test_churn_prediction_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/churn-prediction');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('predictions', $data);
        $this->assertArrayHasKey('total_customers', $data);
        $this->assertArrayHasKey('at_risk_count', $data);
        $this->assertArrayHasKey('critical_count', $data);
    }

    // ── service-summary ─────────────────────────────────────────

    public function test_service_summary_returns_200_for_valid_wo(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/ai/service-summary/'.$wo->id);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('work_order_id', $data);
        $this->assertArrayHasKey('summary_text', $data);
        $this->assertArrayHasKey('metadata', $data);
    }

    public function test_service_summary_handles_nonexistent_wo(): void
    {
        $response = $this->getJson('/api/v1/ai/service-summary/999999');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertStringContainsString('não encontrada', $data['summary_text']);
    }

    // ── equipment-image-analysis ────────────────────────────────

    public function test_equipment_image_analysis_returns_200(): void
    {
        $response = $this->getJson('/api/v1/ai/equipment-image-analysis');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('total_equipments', $data);
        $this->assertArrayHasKey('with_photos', $data);
        $this->assertArrayHasKey('without_photos', $data);
        $this->assertArrayHasKey('coverage_pct', $data);
    }
}
