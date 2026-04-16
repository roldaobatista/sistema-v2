<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialAnalyticsTest extends TestCase
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

    // ── cash-flow-projection ────────────────────────────────────

    public function test_cash_flow_projection_returns_200(): void
    {
        $response = $this->getJson('/api/v1/financial/cash-flow-projection');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    public function test_cash_flow_projection_returns_months(): void
    {
        $response = $this->getJson('/api/v1/financial/cash-flow-projection?months=3');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_cash_flow_projection_entry_structure(): void
    {
        $response = $this->getJson('/api/v1/financial/cash-flow-projection?months=1');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $entry = $data[0];
        $this->assertArrayHasKey('month', $entry);
        $this->assertArrayHasKey('label', $entry);
        $this->assertArrayHasKey('inflows', $entry);
        $this->assertArrayHasKey('outflows', $entry);
        $this->assertArrayHasKey('net', $entry);
        $this->assertArrayHasKey('inflows_count', $entry);
        $this->assertArrayHasKey('outflows_count', $entry);
    }

    public function test_cash_flow_projection_limits_to_12_months(): void
    {
        $response = $this->getJson('/api/v1/financial/cash-flow-projection?months=24');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertLessThanOrEqual(12, count($data));
    }

    // ── DRE ─────────────────────────────────────────────────────

    public function test_dre_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/financial/dre');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'period' => ['from', 'to'],
                'revenue',
                'cogs',
                'gross_profit',
                'gross_margin',
                'operating_expenses',
                'operating_profit',
                'operating_margin',
                'expenses_by_category',
            ]]);
    }

    public function test_dre_accepts_custom_period(): void
    {
        $response = $this->getJson('/api/v1/financial/dre?from=2026-01-01&to=2026-01-31');

        $response->assertOk();
        $period = $response->json('data.period');
        $this->assertEquals('2026-01-01', $period['from']);
        $this->assertEquals('2026-01-31', $period['to']);
    }

    public function test_dre_with_revenue_data(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->paid()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 5000.00,
            'amount_paid' => 5000.00,
            'paid_at' => now()->subMonth(),
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 5000.00,
            'payment_date' => now()->toDateString(),
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();
        $response = $this->getJson("/api/v1/financial/dre?from={$from}&to={$to}");

        $response->assertOk();
        $revenue = (float) $response->json('data.revenue');
        $this->assertGreaterThanOrEqual(5000, $revenue);
    }

    public function test_cash_flow_projection_uses_open_balance_for_partial_payables(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 250.00,
            'status' => 'partial',
            'due_date' => now(),
        ]);

        $entry = $this->getJson('/api/v1/financial/cash-flow-projection?months=1')
            ->assertOk()
            ->json('data.0');

        $this->assertEquals('750.00', $entry['outflows']);
    }

    public function test_cash_flow_projection_ignores_renegotiated_balances(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1200.00,
            'amount_paid' => 200.00,
            'status' => 'renegotiated',
            'due_date' => now()->addDays(4),
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 800.00,
            'amount_paid' => 100.00,
            'status' => 'renegotiated',
            'due_date' => now()->addDays(7),
        ]);

        $entry = $this->getJson('/api/v1/financial/cash-flow-projection?months=1')
            ->assertOk()
            ->json('data.0');

        $this->assertEquals('0.00', $entry['inflows']);
        $this->assertEquals('0.00', $entry['outflows']);
    }

    public function test_dre_uses_payment_date_and_legacy_fallback_without_payments(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivableWithPayment = AccountReceivable::factory()->create([
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
            'payable_id' => $receivableWithPayment->id,
            'received_by' => $this->user->id,
            'amount' => 900.00,
            'payment_date' => '2025-03-10',
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 400.00,
            'amount_paid' => 400.00,
            'status' => 'paid',
            'paid_at' => '2025-03-12',
            'due_date' => '2025-02-01',
        ]);

        $payableWithPayment = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 250.00,
            'amount_paid' => 250.00,
            'status' => 'paid',
            'paid_at' => '2025-01-01',
            'due_date' => '2025-02-05',
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $payableWithPayment->id,
            'received_by' => $this->user->id,
            'amount' => 250.00,
            'payment_date' => '2025-03-15',
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 150.00,
            'amount_paid' => 150.00,
            'status' => 'paid',
            'paid_at' => '2025-03-18',
            'due_date' => '2025-01-10',
        ]);

        $response = $this->getJson('/api/v1/financial/dre?from=2025-03-01&to=2025-03-31')
            ->assertOk();

        $this->assertEquals('1300.00', $response->json('data.revenue'));
        $this->assertEquals('400.00', $response->json('data.operating_expenses'));
    }

    // ── aging-report ────────────────────────────────────────────

    public function test_aging_report_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/financial/aging-report');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'buckets' => [
                    'current' => ['label', 'total', 'count', 'items'],
                    '1_30' => ['label', 'total', 'count', 'items'],
                    '31_60',
                    '61_90',
                    'over_90',
                ],
                'total_outstanding',
                'total_overdue',
                'total_records',
            ]]);
    }

    public function test_aging_report_categorizes_receivables(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Overdue entry (past due date)
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'due_date' => now()->subDays(15),
            'status' => 'pending',
        ]);

        // Current entry (future due date)
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 2000,
            'amount_paid' => 0,
            'due_date' => now()->addDays(10),
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/financial/aging-report');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(2, $data['total_records']);
        $this->assertGreaterThan(0, $data['total_outstanding']);
    }

    public function test_aging_report_ignores_renegotiated_receivables(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 650.00,
            'amount_paid' => 0.00,
            'due_date' => now()->subDays(40),
            'status' => 'renegotiated',
        ]);

        $response = $this->getJson('/api/v1/financial/aging-report')->assertOk();

        $data = $response->json('data');
        $this->assertEquals(0, $data['total_records']);
        $this->assertEquals(0.0, $data['total_outstanding']);
        $this->assertEquals(0.0, $data['total_overdue']);
    }

    // ── expense-allocation ──────────────────────────────────────

    public function test_expense_allocation_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/financial/expense-allocation');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'data',
                'summary' => [
                    'total_expenses_allocated',
                    'total_os_count',
                    'average_margin',
                ],
            ]]);
    }

    public function test_expense_allocation_accepts_date_range(): void
    {
        $response = $this->getJson('/api/v1/financial/expense-allocation?from=2026-01-01&to=2026-01-31');

        $response->assertOk();
    }

    // ── batch-payment-approval GET ──────────────────────────────

    public function test_batch_payment_approval_list_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/financial/batch-payment-approval');

        $response->assertOk();
    }

    public function test_batch_payment_approval_filters_by_due_before(): void
    {
        $response = $this->getJson('/api/v1/financial/batch-payment-approval?due_before=2026-03-31');

        $response->assertOk();
    }

    public function test_batch_payment_approval_filters_by_min_amount(): void
    {
        $response = $this->getJson('/api/v1/financial/batch-payment-approval?min_amount=1000');

        $response->assertOk();
    }

    // ── batch-payment-approval POST ─────────────────────────────

    public function test_approve_batch_payment_processes_payables(): void
    {
        $payable = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 500,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/financial/batch-payment-approval', [
            'ids' => [$payable->id],
            'payment_method' => 'pix',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('processado', $response->json('message'));
    }

    public function test_approve_batch_payment_validates_required_ids(): void
    {
        $response = $this->postJson('/api/v1/financial/batch-payment-approval', []);

        $response->assertStatus(422);
    }

    public function test_approve_batch_payment_validates_ids_exist(): void
    {
        $response = $this->postJson('/api/v1/financial/batch-payment-approval', [
            'ids' => [999999],
        ]);

        $response->assertStatus(422);
    }

    // ── cash-flow-weekly ────────────────────────────────────────

    public function test_cash_flow_weekly_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/financial/cash-flow-weekly');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'period' => ['from', 'to'],
                'initial_balance',
                'days',
                'summary' => [
                    'days_shortage',
                    'days_tight',
                    'min_balance',
                ],
            ]]);
    }

    public function test_cash_flow_weekly_accepts_weeks_param(): void
    {
        $response = $this->getJson('/api/v1/financial/cash-flow-weekly?weeks=2');

        $response->assertOk();
        $days = $response->json('data.days');
        // 2 weeks = 14 days
        $this->assertLessThanOrEqual(14, count($days));
    }

    public function test_cash_flow_weekly_day_structure(): void
    {
        $response = $this->getJson('/api/v1/financial/cash-flow-weekly?weeks=1');

        $response->assertOk();
        $days = $response->json('data.days');
        if (! empty($days)) {
            $day = $days[0];
            $this->assertArrayHasKey('date', $day);
            $this->assertArrayHasKey('label', $day);
            $this->assertArrayHasKey('inflows', $day);
            $this->assertArrayHasKey('outflows', $day);
            $this->assertArrayHasKey('balance_projected', $day);
            $this->assertArrayHasKey('alert', $day);
            $this->assertContains($day['alert'], ['ok', 'tight', 'shortage']);
        }
    }

    public function test_cash_flow_weekly_accepts_initial_balance(): void
    {
        $response = $this->getJson('/api/v1/financial/cash-flow-weekly?initial_balance=10000&weeks=1');

        $response->assertOk();
        $initialBalance = $response->json('data.initial_balance');
        $this->assertEquals(10000, $initialBalance);
    }

    public function test_cash_flow_weekly_ignores_renegotiated_balances(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $targetDate = now()->addDays(3)->toDateString();

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 700.00,
            'amount_paid' => 0.00,
            'status' => 'renegotiated',
            'due_date' => $targetDate,
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
            'amount_paid' => 0.00,
            'status' => 'renegotiated',
            'due_date' => $targetDate,
        ]);

        $days = $this->getJson('/api/v1/financial/cash-flow-weekly?weeks=1')
            ->assertOk()
            ->json('data.days');

        $targetDay = collect($days)->firstWhere('date', $targetDate);

        $this->assertNotNull($targetDay);
        $this->assertEquals('0.00', $targetDay['inflows']);
        $this->assertEquals('0.00', $targetDay['outflows']);
    }
}
