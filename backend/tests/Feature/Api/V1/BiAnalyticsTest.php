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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BiAnalyticsTest extends TestCase
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

    // ── realtime KPIs ───────────────────────────────────────────

    public function test_realtime_kpis_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/bi-analytics/kpis/realtime');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'timestamp',
                'os_today',
                'os_completed_today',
                'os_open',
                'revenue_today',
                'revenue_month',
                'overdue_total',
                'nps_30d',
                'active_technicians',
            ]]);
    }

    public function test_realtime_kpis_counts_todays_work_orders(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/bi-analytics/kpis/realtime');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(3, $data['os_today']);
    }

    public function test_realtime_kpis_use_payment_date_and_open_overdue_balance(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => now()->subDays(5),
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 400.00,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/bi-analytics/kpis/realtime')
            ->assertOk();

        $data = $response->json('data');
        $this->assertEquals(400.0, $data['revenue_today']);
        $this->assertEquals(600.0, $data['overdue_total']);
    }

    public function test_realtime_kpis_include_legacy_paid_amount_in_month_and_ignore_renegotiated_overdue(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 700.00,
            'amount_paid' => 700.00,
            'status' => 'paid',
            'paid_at' => now()->startOfMonth()->addDays(1)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 900.00,
            'amount_paid' => 100.00,
            'status' => 'renegotiated',
            'due_date' => now()->subDays(20)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/bi-analytics/kpis/realtime')->assertOk();

        $data = $response->json('data');
        $this->assertEquals(700.0, $data['revenue_month']);
        $this->assertEquals(0.0, $data['overdue_total']);
    }

    public function test_period_comparison_uses_payment_date_and_completed_at(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'created_at' => '2025-02-01 10:00:00',
            'completed_at' => '2025-03-05 10:00:00',
            'total' => 1000.00,
        ]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 900.00,
            'amount_paid' => 900.00,
            'status' => 'paid',
            'paid_at' => '2025-02-01',
            'due_date' => '2025-01-10',
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 900.00,
            'payment_date' => '2025-03-06',
        ]);

        $payable = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 300.00,
            'amount_paid' => 300.00,
            'status' => 'paid',
            'paid_at' => '2025-02-01',
            'due_date' => '2025-01-05',
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $payable->id,
            'received_by' => $this->user->id,
            'amount' => 300.00,
            'payment_date' => '2025-03-07',
        ]);

        $response = $this->getJson('/api/v1/bi-analytics/comparison?'.http_build_query([
            'period1_from' => '2025-02-01',
            'period1_to' => '2025-02-28',
            'period2_from' => '2025-03-01',
            'period2_to' => '2025-03-31',
        ]))->assertOk();

        $comparison = $response->json('data.comparison');
        $this->assertEquals(0, $comparison['os_completed']['period_1']);
        $this->assertEquals(1, $comparison['os_completed']['period_2']);
        $this->assertEquals(0.0, $comparison['revenue']['period_1']);
        $this->assertEquals(900.0, $comparison['revenue']['period_2']);
        $this->assertEquals(300.0, $comparison['expenses']['period_2']);
    }

    public function test_period_comparison_includes_legacy_paid_amount_without_payment_records(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 450.00,
            'amount_paid' => 450.00,
            'status' => 'paid',
            'paid_at' => '2025-03-08',
            'due_date' => '2025-02-15',
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 120.00,
            'amount_paid' => 120.00,
            'status' => 'paid',
            'paid_at' => '2025-03-09',
            'due_date' => '2025-02-18',
        ]);

        $response = $this->getJson('/api/v1/bi-analytics/comparison?'.http_build_query([
            'period1_from' => '2025-02-01',
            'period1_to' => '2025-02-28',
            'period2_from' => '2025-03-01',
            'period2_to' => '2025-03-31',
        ]))->assertOk();

        $comparison = $response->json('data.comparison');
        $this->assertEquals(450.0, $comparison['revenue']['period_2']);
        $this->assertEquals(120.0, $comparison['expenses']['period_2']);
    }

    // ── profitability ───────────────────────────────────────────

    public function test_profitability_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/bi-analytics/profitability');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'period' => ['from', 'to'],
                'work_orders',
                'totals' => ['revenue', 'total_cost', 'profit', 'avg_margin'],
            ]]);
    }

    public function test_profitability_accepts_date_range(): void
    {
        $response = $this->getJson('/api/v1/bi-analytics/profitability?from=2026-01-01&to=2026-01-31');

        $response->assertOk();
        $period = $response->json('data.period');
        $this->assertEquals('2026-01-01', $period['from']);
        $this->assertEquals('2026-01-31', $period['to']);
    }

    public function test_profitability_with_completed_wo(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'total' => 5000,
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $response = $this->getJson("/api/v1/bi-analytics/profitability?from={$from}&to={$to}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data['work_orders']);
    }

    // ── anomaly detection ───────────────────────────────────────

    public function test_anomaly_detection_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/bi-analytics/anomalies');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'anomalies_found',
                'anomalies',
                'checked_at',
            ]]);
    }

    // ── scheduled exports ───────────────────────────────────────

    public function test_scheduled_exports_list_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/bi-analytics/exports/scheduled');

        $response->assertOk();
    }

    public function test_create_scheduled_export_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/bi-analytics/exports/scheduled', [
            'report_type' => 'financial',
            'format' => 'xlsx',
            'frequency' => 'monthly',
            'recipients' => ['admin@example.com'],
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
    }

    public function test_create_scheduled_export_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/bi-analytics/exports/scheduled', []);

        $response->assertStatus(422);
    }

    public function test_create_scheduled_export_validates_report_type(): void
    {
        $response = $this->postJson('/api/v1/bi-analytics/exports/scheduled', [
            'report_type' => 'invalid_type',
            'format' => 'xlsx',
            'frequency' => 'monthly',
            'recipients' => ['admin@example.com'],
        ]);

        $response->assertStatus(422);
    }

    public function test_create_scheduled_export_validates_format(): void
    {
        $response = $this->postJson('/api/v1/bi-analytics/exports/scheduled', [
            'report_type' => 'financial',
            'format' => 'doc',
            'frequency' => 'monthly',
            'recipients' => ['admin@example.com'],
        ]);

        $response->assertStatus(422);
    }

    public function test_create_scheduled_export_validates_recipients_email(): void
    {
        $response = $this->postJson('/api/v1/bi-analytics/exports/scheduled', [
            'report_type' => 'financial',
            'format' => 'xlsx',
            'frequency' => 'monthly',
            'recipients' => ['not-an-email'],
        ]);

        $response->assertStatus(422);
    }

    public function test_delete_scheduled_export(): void
    {
        $id = DB::table('scheduled_report_exports')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'report_type' => 'financial',
            'format' => 'xlsx',
            'frequency' => 'monthly',
            'recipients' => json_encode(['admin@example.com']),
            'filters' => json_encode([]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/bi-analytics/exports/scheduled/{$id}");

        $response->assertOk();
        $this->assertDatabaseMissing('scheduled_report_exports', ['id' => $id]);
    }

    // ── period comparison ───────────────────────────────────────

    public function test_period_comparison_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/bi-analytics/comparison?'.http_build_query([
            'period1_from' => '2026-01-01',
            'period1_to' => '2026-01-31',
            'period2_from' => '2026-02-01',
            'period2_to' => '2026-02-28',
        ]));

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'period_1' => ['from', 'to'],
                'period_2' => ['from', 'to'],
                'comparison',
            ]]);

        $comparison = $response->json('data.comparison');
        $expectedMetrics = ['os_created', 'os_completed', 'revenue', 'expenses', 'new_customers', 'avg_ticket'];
        foreach ($expectedMetrics as $metric) {
            $this->assertArrayHasKey($metric, $comparison);
            $this->assertArrayHasKey('period_1', $comparison[$metric]);
            $this->assertArrayHasKey('period_2', $comparison[$metric]);
            $this->assertArrayHasKey('change_percent', $comparison[$metric]);
            $this->assertArrayHasKey('trend', $comparison[$metric]);
        }
    }

    public function test_period_comparison_validates_required_dates(): void
    {
        $response = $this->getJson('/api/v1/bi-analytics/comparison?period1_from=2026-01-01');

        $response->assertStatus(422);
    }

    public function test_period_comparison_validates_date_order(): void
    {
        $response = $this->getJson('/api/v1/bi-analytics/comparison?'.http_build_query([
            'period1_from' => '2026-01-31',
            'period1_to' => '2026-01-01',
            'period2_from' => '2026-02-01',
            'period2_to' => '2026-02-28',
        ]));

        $response->assertStatus(422);
    }
}
