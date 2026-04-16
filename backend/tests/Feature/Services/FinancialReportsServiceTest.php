<?php

namespace Tests\Feature\Services;

use App\Enums\FinancialStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CashFlowProjectionService;
use App\Services\DREService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos reais do DREService e CashFlowProjectionService.
 */
class FinancialReportsServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin);
    }

    // ═══ DREService ═══

    public function test_dre_empty_period_returns_zeros(): void
    {
        $svc = new DREService;
        $result = $svc->generate(
            Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'), $this->tenant->id
        );

        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('receitas_brutas', $result);
        $this->assertArrayHasKey('receitas_liquidas', $result);
        $this->assertArrayHasKey('custos_servicos', $result);
        $this->assertArrayHasKey('lucro_bruto', $result);
        $this->assertArrayHasKey('despesas_operacionais', $result);
        $this->assertArrayHasKey('resultado_liquido', $result);
        $this->assertArrayHasKey('margem_bruta_percent', $result);
        $this->assertArrayHasKey('margem_liquida_percent', $result);
        $this->assertArrayHasKey('by_month', $result);
        $this->assertEquals('0.00', $result['receitas_brutas']);
    }

    public function test_dre_with_receivables_shows_revenue(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '5000.00',
            'amount_paid' => '5000.00',
            'due_date' => '2026-01-15',
            'paid_at' => '2026-01-15',
            'status' => FinancialStatus::PAID,
        ]);

        $svc = new DREService;
        $result = $svc->generate(
            Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'), $this->tenant->id
        );

        $this->assertGreaterThan(0, (float) $result['receitas_brutas']);
    }

    public function test_dre_margins_zero_when_no_revenue(): void
    {
        $svc = new DREService;
        $result = $svc->generate(
            Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'), $this->tenant->id
        );

        $this->assertEquals('0.00', $result['margem_bruta_percent']);
        $this->assertEquals('0.00', $result['margem_liquida_percent']);
    }

    public function test_dre_period_dates(): void
    {
        $svc = new DREService;
        $result = $svc->generate(
            Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'), $this->tenant->id
        );

        $this->assertEquals('2026-03-01', $result['period']['from']);
        $this->assertEquals('2026-03-31', $result['period']['to']);
    }

    public function test_dre_by_month_structure(): void
    {
        $svc = new DREService;
        $result = $svc->generate(
            Carbon::parse('2026-01-01'), Carbon::parse('2026-03-31'), $this->tenant->id
        );

        $this->assertCount(3, $result['by_month']);
        $this->assertArrayHasKey('month', $result['by_month'][0]);
        $this->assertArrayHasKey('receitas', $result['by_month'][0]);
        $this->assertArrayHasKey('custos', $result['by_month'][0]);
        $this->assertArrayHasKey('despesas', $result['by_month'][0]);
        $this->assertArrayHasKey('resultado', $result['by_month'][0]);
    }

    public function test_dre_costs_from_expenses_linked_to_os(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'amount' => '800.00',
            'expense_date' => '2026-02-10',
            'affects_net_value' => true,
            'status' => 'approved',
        ]);

        $svc = new DREService;
        $result = $svc->generate(
            Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'), $this->tenant->id
        );

        $this->assertGreaterThanOrEqual(800, (float) $result['custos_servicos']);
    }

    public function test_dre_operational_expenses_without_os(): void
    {
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => null,
            'amount' => '500.00',
            'expense_date' => '2026-02-10',
            'status' => 'approved',
        ]);

        $svc = new DREService;
        $result = $svc->generate(
            Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'), $this->tenant->id
        );

        $this->assertGreaterThanOrEqual(500, (float) $result['despesas_operacionais']);
    }

    public function test_dre_rejected_expenses_excluded(): void
    {
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => null,
            'amount' => '9999.00',
            'expense_date' => '2026-02-10',
            'status' => 'rejected',
        ]);

        $svc = new DREService;
        $result = $svc->generate(
            Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'), $this->tenant->id
        );

        $this->assertLessThan(9999, (float) $result['despesas_operacionais']);
    }

    // ═══ CashFlowProjectionService ═══

    public function test_cashflow_empty_period(): void
    {
        $svc = new CashFlowProjectionService;
        $result = $svc->project(
            Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'), $this->tenant->id
        );

        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('by_week', $result);
        $this->assertEquals('0.00', $result['summary']['entradas_previstas']);
        $this->assertEquals('0.00', $result['summary']['saidas_previstas']);
    }

    public function test_cashflow_with_pending_receivable(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '3000.00',
            'amount_paid' => '0.00',
            'due_date' => '2026-02-15',
            'status' => FinancialStatus::PENDING,
        ]);

        $svc = new CashFlowProjectionService;
        $result = $svc->project(
            Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'), $this->tenant->id
        );

        $this->assertGreaterThan(0, (float) $result['summary']['entradas_previstas']);
    }

    public function test_cashflow_with_pending_payable(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '2000.00',
            'amount_paid' => '0.00',
            'due_date' => '2026-02-20',
            'status' => FinancialStatus::PENDING,
        ]);

        $svc = new CashFlowProjectionService;
        $result = $svc->project(
            Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'), $this->tenant->id
        );

        $this->assertGreaterThan(0, (float) $result['summary']['saidas_previstas']);
    }

    public function test_cashflow_paid_excluded_from_projections(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1500.00',
            'amount_paid' => '1500.00',
            'due_date' => '2026-02-10',
            'status' => FinancialStatus::PAID,
        ]);

        $svc = new CashFlowProjectionService;
        $result = $svc->project(
            Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'), $this->tenant->id
        );

        // Paid items should NOT appear in "previstas"
        $this->assertEquals('0.00', $result['summary']['saidas_previstas']);
    }

    public function test_cashflow_weekly_breakdown(): void
    {
        $svc = new CashFlowProjectionService;
        $result = $svc->project(
            Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'), $this->tenant->id
        );

        $this->assertGreaterThanOrEqual(4, count($result['by_week']));
        $this->assertArrayHasKey('week', $result['by_week'][0]);
        $this->assertArrayHasKey('from', $result['by_week'][0]);
        $this->assertArrayHasKey('to', $result['by_week'][0]);
        $this->assertArrayHasKey('entradas_previstas', $result['by_week'][0]);
        $this->assertArrayHasKey('saidas_previstas', $result['by_week'][0]);
    }

    public function test_cashflow_saldo_calculation(): void
    {
        $svc = new CashFlowProjectionService;
        $result = $svc->project(
            Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'), $this->tenant->id
        );

        $expected = bcsub(
            $result['summary']['total_entradas'],
            $result['summary']['total_saidas'],
            2
        );
        $this->assertEquals($expected, $result['summary']['saldo_previsto']);
    }

    public function test_cashflow_tenant_isolation(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '5000.00',
            'amount_paid' => '0.00',
            'due_date' => '2026-02-15',
            'status' => FinancialStatus::PENDING,
        ]);

        $otherTenant = Tenant::factory()->create();
        $svc = new CashFlowProjectionService;
        $result = $svc->project(
            Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'), $otherTenant->id
        );

        $this->assertEquals('0.00', $result['summary']['entradas_previstas']);
    }
}
