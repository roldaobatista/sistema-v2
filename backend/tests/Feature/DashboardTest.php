<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dashboard API Tests — validates /dashboard-stats with real data assertions,
 * correct date range filtering, and multi-tenant isolation.
 */
class DashboardTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── Structure ────────────────────────────────────────────────────────────

    public function test_dashboard_returns_all_required_keys(): void
    {
        $response = $this->getJson('/api/v1/dashboard-stats');
        $response->assertOk();

        $requiredKeys = [
            'open_os', 'in_progress_os', 'completed_month',
            'revenue_month', 'pending_commissions', 'expenses_month',
            'recent_os', 'top_technicians',
            'eq_overdue', 'eq_due_7', 'eq_alerts',
            'crm_open_deals', 'crm_won_month', 'crm_revenue_month', 'crm_pending_followups',
            'receivables_pending', 'receivables_overdue',
            'payables_pending', 'payables_overdue',
            'net_revenue',
            'sla_total', 'sla_response_breached', 'sla_resolution_breached',
            'monthly_revenue', 'avg_completion_hours',
            'prev_open_os', 'prev_completed_month', 'prev_revenue_month',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $response->json('data'), "Missing key: {$key}");
        }
    }

    public function test_dashboard_monthly_revenue_has_exactly_six_months(): void
    {
        $data = $this->getJson('/api/v1/dashboard-stats')->assertOk()->json('data');

        $this->assertIsArray($data['monthly_revenue']);
        $this->assertCount(6, $data['monthly_revenue']);

        foreach ($data['monthly_revenue'] as $point) {
            $this->assertArrayHasKey('month', $point);
            $this->assertArrayHasKey('total', $point);
            $this->assertIsString($point['month']);
            $this->assertIsNumeric($point['total']);
        }
    }

    // ── Work Order counts ────────────────────────────────────────────────────

    public function test_open_os_count_is_correct(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
        // Completed/cancelled should NOT count as open
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'updated_at' => now(),
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats')->assertOk()->json('data');

        $this->assertEquals(3, $data['open_os']); // 2 open + 1 in_progress
        $this->assertEquals(1, $data['in_progress_os']);
    }

    public function test_completed_month_counts_only_within_date_range(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Completed in March 2025 — should count
        WorkOrder::factory(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'completed_at' => '2025-03-15 10:00:00',
            'total' => 100.00,
        ]);

        // Completed in February 2025 — should NOT count when filtering March
        WorkOrder::factory(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'completed_at' => '2025-02-15 10:00:00',
            'total' => 500.00,
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats?date_from=2025-03-01&date_to=2025-03-31')->assertOk()->json('data');

        $this->assertEquals(3, $data['completed_month']);
        $this->assertEquals(300.00, $data['revenue_month']);
    }

    public function test_prev_period_data_returns_previous_month_stats(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Completed in March 2025 (current period)
        WorkOrder::factory(4)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'completed_at' => '2025-03-10 10:00:00',
            'total' => 200.00,
        ]);

        // Completed in February 2025 (previous period)
        WorkOrder::factory(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'completed_at' => '2025-02-15 10:00:00',
            'total' => 150.00,
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats?date_from=2025-03-01&date_to=2025-03-31')->assertOk()->json('data');

        // Current period
        $this->assertEquals(4, $data['completed_month']);
        $this->assertEquals(800.00, $data['revenue_month']);

        // Previous period (February)
        $this->assertEquals(2, $data['prev_completed_month']);
        $this->assertEquals(300.00, $data['prev_revenue_month']);
    }

    // ── Multi-tenant isolation ───────────────────────────────────────────────

    public function test_dashboard_only_shows_own_tenant_work_orders(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        // Other tenant has 10 open OS — must NOT appear in our counts
        app()->instance('current_tenant_id', $otherTenant->id);
        WorkOrder::factory(10)->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        // Restore to our tenant
        app()->instance('current_tenant_id', $this->tenant->id);

        $data = $this->getJson('/api/v1/dashboard-stats')->assertOk()->json('data');

        $this->assertEquals(0, $data['open_os']);
    }

    public function test_dashboard_only_shows_own_tenant_receivables(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        // Other tenant's receivables — must NOT appear in our totals
        app()->instance('current_tenant_id', $otherTenant->id);
        AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'amount' => 99999.00,
            'amount_paid' => 0,
            'status' => AccountReceivable::STATUS_PENDING,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);

        // Our tenant has R$ 500 pending
        $myCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $myCustomer->id,
            'amount' => 500.00,
            'amount_paid' => 0,
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats')->assertOk()->json('data');

        $this->assertEquals(500.00, $data['receivables_pending']);
    }

    // ── Financial KPIs ───────────────────────────────────────────────────────

    public function test_receivables_pending_sums_pending_and_partial_statuses(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => AccountReceivable::STATUS_PENDING,
        ]);
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'amount' => 500.00,
            'amount_paid' => 200.00,
            'status' => AccountReceivable::STATUS_PARTIAL,
        ]);
        // Paid — must NOT be included
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'amount' => 300.00,
            'amount_paid' => 300.00,
            'status' => AccountReceivable::STATUS_PAID,
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats')->assertOk()->json('data');

        $this->assertEquals(1300.00, $data['receivables_pending']);
    }

    public function test_receivables_overdue_only_counts_past_due_date(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Overdue (due date in the past)
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'amount' => 200.00,
            'amount_paid' => 0,
            'due_date' => now()->subDays(5),
            'status' => AccountReceivable::STATUS_PENDING,
        ]);
        // Not overdue (due date in the future) — counts as pending but not overdue
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'amount' => 800.00,
            'amount_paid' => 0,
            'due_date' => now()->addDays(10),
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats')->assertOk()->json('data');

        $this->assertEquals(1000.00, $data['receivables_pending']); // both, open balance
        $this->assertEquals(200.00, $data['receivables_overdue']);   // only the overdue one
    }

    public function test_dashboard_completed_metrics_use_completed_at_not_updated_at(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'completed_at' => '2025-02-15 10:00:00',
            'updated_at' => '2025-03-05 10:00:00',
            'total' => 900.00,
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats?date_from=2025-02-01&date_to=2025-02-28')
            ->assertOk()
            ->json('data');

        $this->assertEquals(1, $data['completed_month']);
        $this->assertEquals(900.00, $data['revenue_month']);

        $marchData = $this->getJson('/api/v1/dashboard-stats?date_from=2025-03-01&date_to=2025-03-31')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $marchData['completed_month']);
        $this->assertEquals(0.0, $marchData['revenue_month']);
    }

    public function test_financial_summary_uses_payment_date_and_open_balance(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => AccountReceivable::STATUS_PENDING,
            'due_date' => '2025-02-10',
            'paid_at' => null,
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
            'amount' => 800.00,
            'amount_paid' => 0,
            'status' => AccountPayable::STATUS_PENDING,
            'due_date' => '2025-02-12',
            'paid_at' => null,
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $payable->id,
            'received_by' => $this->user->id,
            'amount' => 200.00,
            'payment_date' => '2025-03-07',
        ]);

        $data = $this->getJson('/api/v1/financial/summary?date_from=2025-03-01&date_to=2025-03-31')
            ->assertOk()
            ->json('data');

        $this->assertEquals(700.00, $data['receivables']['pending_amount']);
        $this->assertEquals(300.00, $data['receivables']['paid_period']);
        $this->assertEquals(600.00, $data['payables']['pending_amount']);
        $this->assertEquals(200.00, $data['payables']['paid_period']);
        $this->assertEquals(100.00, $data['balance']);
    }

    public function test_net_revenue_equals_revenue_minus_expenses(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'updated_at' => now(),
            'completed_at' => now(),
            'total' => 1000.00,
        ]);

        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => ExpenseStatus::APPROVED,
            'expense_date' => now(),
            'amount' => 300.00,
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats')->assertOk()->json('data');

        $this->assertEquals(1000.00, $data['revenue_month']);
        $this->assertEquals(300.00, $data['expenses_month']);
        $this->assertEquals(700.00, $data['net_revenue']);
    }

    // ── Date range filter ────────────────────────────────────────────────────

    public function test_date_range_filter_excludes_data_outside_range(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Completed in January 2025 — excluded when filtering Feb 2025
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'updated_at' => '2025-01-15 10:00:00',
            'completed_at' => '2025-01-15 10:00:00',
            'total' => 9999.00,
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats?date_from=2025-02-01&date_to=2025-02-28')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $data['completed_month']);
        $this->assertEquals(0.0, $data['revenue_month']);
    }

    public function test_date_range_filter_includes_data_within_range(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'updated_at' => '2025-02-15 10:00:00',
            'completed_at' => '2025-02-15 10:00:00',
            'total' => 500.00,
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats?date_from=2025-02-01&date_to=2025-02-28')
            ->assertOk()
            ->json('data');

        $this->assertEquals(1, $data['completed_month']);
        $this->assertEquals(500.00, $data['revenue_month']);
    }

    public function test_invalid_date_returns_422(): void
    {
        $this->getJson('/api/v1/dashboard-stats?date_from=not-a-date')
            ->assertUnprocessable();
    }

    public function test_date_to_before_date_from_returns_422(): void
    {
        $this->getJson('/api/v1/dashboard-stats?date_from=2025-06-01&date_to=2025-05-01')
            ->assertUnprocessable();
    }

    // ── Limits ───────────────────────────────────────────────────────────────

    public function test_recent_os_is_capped_at_10_even_with_more_data(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory(15)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $data = $this->getJson('/api/v1/dashboard-stats')->assertOk()->json('data');

        $this->assertCount(10, $data['recent_os']);
    }

    public function test_top_technicians_is_capped_at_5(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $technicians = User::factory(8)->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        foreach ($technicians as $tech) {
            WorkOrder::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'assigned_to' => $tech->id,
                'status' => WorkOrder::STATUS_COMPLETED,
                'updated_at' => now(),
            ]);
        }

        $data = $this->getJson('/api/v1/dashboard-stats')->assertOk()->json('data');

        $this->assertLessThanOrEqual(5, count($data['top_technicians']));
    }
}
