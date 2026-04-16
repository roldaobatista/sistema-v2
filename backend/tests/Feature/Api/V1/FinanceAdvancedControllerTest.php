<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceAdvancedControllerTest extends TestCase
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

    public function test_cash_flow_projection_uses_open_balance_and_payment_date(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => now(),
        ]);

        $payable = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 800.00,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => now(),
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $payable->id,
            'received_by' => $this->user->id,
            'amount' => 200.00,
            'payment_date' => now()->toDateString(),
        ]);

        $data = $this->getJson('/api/v1/finance-advanced/cash-flow/projection?months=1')
            ->assertOk()
            ->json('data');

        $this->assertEquals('-200.00', $data['current_balance']);
        $this->assertEquals('1000', $data['projection'][0]['receivables']);
        $this->assertEquals('600', $data['projection'][0]['payables']);
    }

    public function test_cash_flow_projection_ignores_renegotiated_balances(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 900.00,
            'amount_paid' => 100.00,
            'status' => 'renegotiated',
            'due_date' => now(),
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 700.00,
            'amount_paid' => 50.00,
            'status' => 'renegotiated',
            'due_date' => now(),
        ]);

        $data = $this->getJson('/api/v1/finance-advanced/cash-flow/projection?months=1')
            ->assertOk()
            ->json('data');

        $this->assertEquals('0', $data['projection'][0]['receivables']);
        $this->assertEquals('0', $data['projection'][0]['payables']);
    }

    public function test_dre_by_cost_center_uses_payment_records(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $chart = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '3.01',
            'name' => 'Receita Servicos',
            'type' => ChartOfAccount::TYPE_REVENUE,
            'is_system' => false,
            'is_active' => true,
        ]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'chart_of_account_id' => $chart->id,
            'amount' => 900.00,
            'amount_paid' => 900.00,
            'status' => 'paid',
            'paid_at' => '2025-02-01',
            'due_date' => '2025-01-01',
        ]);

        $payable = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'chart_of_account_id' => $chart->id,
            'amount' => 300.00,
            'amount_paid' => 300.00,
            'status' => 'paid',
            'paid_at' => '2025-02-01',
            'due_date' => '2025-01-01',
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 900.00,
            'payment_date' => '2025-03-10',
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $payable->id,
            'received_by' => $this->user->id,
            'amount' => 300.00,
            'payment_date' => '2025-03-12',
        ]);

        $data = $this->getJson('/api/v1/finance-advanced/dre/cost-center?from=2025-03-01&to=2025-03-31')
            ->assertOk()
            ->json('data');

        $this->assertEquals('900', $data['totals']['revenue']);
        $this->assertEquals('300', $data['totals']['expenses']);
        $this->assertEquals('600.00', $data['totals']['profit']);
    }

    public function test_delinquency_dashboard_uses_open_balance(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 400.00,
            'status' => 'partial',
            'due_date' => now()->subDays(10),
        ]);

        $data = $this->getJson('/api/v1/finance-advanced/delinquency/dashboard')
            ->assertOk()
            ->json('data');

        $this->assertEquals(600.0, $data['total_overdue']);
        $this->assertEquals(600.0, $data['aging_buckets']['1-30']);
        $this->assertEquals(600.0, $data['top_customers'][0]['total_due']);
    }

    public function test_delinquency_dashboard_ignores_renegotiated_receivables(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 850.00,
            'amount_paid' => 0.00,
            'status' => 'renegotiated',
            'due_date' => now()->subDays(18),
        ]);

        $data = $this->getJson('/api/v1/finance-advanced/delinquency/dashboard')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0.0, $data['total_overdue']);
        $this->assertEquals(0, $data['overdue_count']);
        $this->assertSame([], $data['top_customers']);
        $this->assertEquals(0, $data['delinquency_rate']);
    }

    public function test_collection_rules_include_overdue_status_and_open_balance(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'cliente@example.com',
            'phone' => '65999999999',
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1200.00,
            'amount_paid' => 450.00,
            'status' => 'overdue',
            'due_date' => now()->subDays(12),
        ]);

        $data = $this->getJson('/api/v1/finance-advanced/collection-rules')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data['data']);
        $this->assertEquals('750.00', $data['data'][0]['amount']);
        $this->assertEquals(12, $data['data'][0]['days_overdue']);
        $this->assertEquals(750.0, $data['summary']['total_amount']);
        $this->assertEquals(1, $data['summary']['total_overdue']);
    }
}
