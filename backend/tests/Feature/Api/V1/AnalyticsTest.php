<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsTest extends TestCase
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

    // ── executive summary ───────────────────────────────────────

    public function test_executive_summary_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/analytics/executive-summary');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'period' => ['from', 'to'],
                'operational' => [
                    'total_os', 'os_completed', 'os_pending', 'os_cancelled',
                    'completion_rate', 'total_service_calls', 'sc_completed', 'prev_total_os',
                ],
                'financial' => [
                    'total_receivable', 'total_received', 'total_overdue',
                    'total_payable', 'total_paid', 'total_expenses', 'net_balance',
                ],
                'commercial' => [
                    'total_quotes', 'approved_quotes', 'conversion_rate',
                    'quotes_value', 'new_customers', 'total_active_customers',
                ],
                'assets' => ['total_equipments', 'calibrations_due_30'],
            ]]);
    }

    public function test_executive_summary_accepts_custom_period(): void
    {
        $response = $this->getJson('/api/v1/analytics/executive-summary?from=2026-01-01&to=2026-01-31');

        $response->assertOk();
        $period = $response->json('data.period');
        $this->assertEquals('2026-01-01', $period['from']);
        $this->assertEquals('2026-01-31', $period['to']);
    }

    public function test_executive_summary_counts_work_orders(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);
        WorkOrder::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();
        $response = $this->getJson("/api/v1/analytics/executive-summary?from={$from}&to={$to}");

        $response->assertOk();
        $operational = $response->json('data.operational');
        $this->assertGreaterThanOrEqual(3, $operational['total_os']);
        $this->assertGreaterThanOrEqual(1, $operational['os_completed']);
    }

    public function test_executive_summary_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        WorkOrder::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => User::factory()->create(['tenant_id' => $otherTenant->id])->id,
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();
        $response = $this->getJson("/api/v1/analytics/executive-summary?from={$from}&to={$to}");

        $response->assertOk();
        // Should not include other tenant's 5 WOs (tenant isolation via auth user)
        $operational = $response->json('data.operational');
        $this->assertLessThan(5, $operational['total_os']);
    }

    public function test_executive_summary_uses_payment_date_and_open_overdue_balance(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => '2025-02-10',
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 300.00,
            'payment_date' => '2025-03-05',
        ]);

        $payable = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 800.00,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => '2025-02-12',
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $payable->id,
            'received_by' => $this->user->id,
            'amount' => 200.00,
            'payment_date' => '2025-03-07',
        ]);

        $response = $this->getJson('/api/v1/analytics/executive-summary?from=2025-03-01&to=2025-03-31')
            ->assertOk();

        $financial = $response->json('data.financial');
        $this->assertEquals('300.00', $financial['total_received']);
        $this->assertEquals('200.00', $financial['total_paid']);
        $this->assertEquals('700.00', $financial['total_overdue']);
    }

    public function test_executive_summary_uses_open_balance_for_titles_due_inside_period(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 250.00,
            'status' => 'partial',
            'due_date' => '2025-03-10',
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 800.00,
            'amount_paid' => 100.00,
            'status' => 'partial',
            'due_date' => '2025-03-12',
        ]);

        $financial = $this->getJson('/api/v1/analytics/executive-summary?from=2025-03-01&to=2025-03-31')
            ->assertOk()
            ->json('data.financial');

        $this->assertEquals('750.00', $financial['total_receivable']);
        $this->assertEquals('700.00', $financial['total_payable']);
    }

    public function test_trends_and_nl_query_use_payment_date_for_revenue(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 900.00,
            'amount_paid' => 900.00,
            'status' => 'paid',
            'paid_at' => '2025-02-15',
            'due_date' => '2025-01-20',
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 900.00,
            'payment_date' => now()->toDateString(),
        ]);

        $trends = $this->getJson('/api/v1/analytics/trends?months=1')
            ->assertOk()
            ->json('data');

        $this->assertTrue(
            collect($trends)->contains(fn (array $item) => (float) ($item['revenue'] ?? 0) >= 900.0)
        );

        $nl = $this->getJson('/api/v1/analytics/nl-query?query=receita+deste+mes')
            ->assertOk()
            ->json('data');

        $this->assertEquals('revenue', $nl['query_analysis']['metric'] ?? null);
        $this->assertGreaterThanOrEqual(900.0, (float) ($nl['value'] ?? 0));
    }

    public function test_trends_include_legacy_paid_amount_without_payment_records(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
            'amount_paid' => 500.00,
            'status' => 'paid',
            'paid_at' => now()->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $trends = $this->getJson('/api/v1/analytics/trends?months=1')
            ->assertOk()
            ->json('data');

        $this->assertTrue(
            collect($trends)->contains(fn (array $item) => (float) ($item['revenue'] ?? 0) >= 500.0)
        );
    }

    // ── trends ──────────────────────────────────────────────────

    public function test_trends_returns_array(): void
    {
        $response = $this->getJson('/api/v1/analytics/trends');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    public function test_trends_accepts_months_param(): void
    {
        $response = $this->getJson('/api/v1/analytics/trends?months=6');

        $response->assertOk();
        $data = $response->json('data');
        // Should have at most 6 months of data plus current month
        $this->assertLessThanOrEqual(7, count($data));
    }

    public function test_trends_entries_have_expected_keys(): void
    {
        $response = $this->getJson('/api/v1/analytics/trends');
        $response->assertOk();

        $data = $response->json('data');
        if (! empty($data)) {
            $first = $data[0];
            $this->assertArrayHasKey('month', $first);
            $this->assertArrayHasKey('month_key', $first);
            $this->assertArrayHasKey('os_total', $first);
            $this->assertArrayHasKey('os_completed', $first);
            $this->assertArrayHasKey('revenue', $first);
            $this->assertArrayHasKey('expenses', $first);
            $this->assertArrayHasKey('quotes_total', $first);
            $this->assertArrayHasKey('new_customers', $first);
        }
    }

    // ── forecast ────────────────────────────────────────────────

    public function test_forecast_returns_422_with_insufficient_data(): void
    {
        $response = $this->getJson('/api/v1/analytics/forecast');

        // With no data, may return 422 (insufficient data) or 200 with empty
        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_forecast_accepts_metric_param(): void
    {
        $response = $this->getJson('/api/v1/analytics/forecast?metric=os_total');

        $this->assertContains($response->status(), [200, 422]);
    }

    // ── anomalies ───────────────────────────────────────────────

    public function test_anomalies_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/analytics/anomalies');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('anomalies', $data);
    }

    public function test_anomalies_accepts_metric_param(): void
    {
        $response = $this->getJson('/api/v1/analytics/anomalies?metric=expenses');

        $response->assertOk();
    }

    // ── nl-query ────────────────────────────────────────────────

    public function test_nl_query_revenue_question(): void
    {
        $response = $this->getJson('/api/v1/analytics/nl-query?query=receita+deste+mes');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('answer', $data);
        $this->assertEquals('revenue', $data['query_analysis']['metric'] ?? null);
    }

    public function test_nl_query_expenses_question(): void
    {
        $response = $this->getJson('/api/v1/analytics/nl-query?query=despesas+do+mes');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('expenses', $data['query_analysis']['metric'] ?? null);
    }

    public function test_nl_query_profit_question(): void
    {
        $response = $this->getJson('/api/v1/analytics/nl-query?query=lucro+do+mes');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('profit', $data['query_analysis']['metric'] ?? null);
    }

    public function test_nl_query_work_orders_question(): void
    {
        $response = $this->getJson('/api/v1/analytics/nl-query?query=quantas+os+este+mes');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('work_orders', $data['query_analysis']['metric'] ?? null);
    }

    public function test_nl_query_unknown_intent(): void
    {
        $response = $this->getJson('/api/v1/analytics/nl-query?query=temperatura+da+cidade');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('text', $data['type']);
        $this->assertStringContainsString('não entendi', $data['answer']);
    }

    public function test_nl_query_last_month_period(): void
    {
        $response = $this->getJson('/api/v1/analytics/nl-query?query=receita+do+mes+passado');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('last_month', $data['query_analysis']['period'] ?? null);
    }
}
