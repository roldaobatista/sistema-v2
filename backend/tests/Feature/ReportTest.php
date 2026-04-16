<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Enums\ServiceCallStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TechnicianCashFund;
use App\Models\TechnicianCashTransaction;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportTest extends TestCase
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
            'is_active' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $exportPermissions = [
            'reports.os_report.export',
            'reports.productivity_report.export',
            'reports.financial_report.export',
            'reports.commission_report.export',
            'reports.margin_report.export',
            'reports.quotes_report.export',
            'reports.service_calls_report.export',
            'reports.technician_cash_report.export',
            'reports.crm_report.export',
            'reports.equipments_report.export',
            'reports.suppliers_report.export',
            'reports.stock_report.export',
            'reports.customers_report.export',
        ];

        foreach ($exportPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->user->syncPermissions($exportPermissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_work_orders_report_is_tenant_scoped(): void
    {
        $otherTenant = Tenant::factory()->create();

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
            'total' => 1200,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'created_by' => User::factory()->create(['tenant_id' => $otherTenant->id, 'current_tenant_id' => $otherTenant->id])->id,
            'status' => WorkOrder::STATUS_OPEN,
            'total' => 5000,
        ]);

        $response = $this->getJson('/api/v1/reports/work-orders');

        $response->assertOk()
            ->assertJsonPath('data.by_status.0.count', 1);
        $this->assertSame(1200.0, (float) $response->json('data.by_status.0.total'));
    }

    public function test_productivity_report_uses_technician_id_and_returns_data(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'completed_at' => now(),
            'status' => WorkOrder::STATUS_COMPLETED,
            'total' => 500,
        ]);

        TimeEntry::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'technician_id' => $this->user->id,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_minutes' => 60,
            'type' => 'work',
        ]);

        $response = $this->getJson('/api/v1/reports/productivity');

        $response->assertOk();
        $this->assertSame($this->user->id, $response->json('data.technicians.0.id'));
        $this->assertSame(60, (int) $response->json('data.technicians.0.work_minutes'));
        $this->assertSame($this->user->id, $response->json('data.completed_by_tech.0.assignee_id'));
        $this->assertSame($this->user->name, $response->json('data.completed_by_tech.0.assignee.name'));
    }

    public function test_quotes_report_counts_invoiced_as_converted(): void
    {
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_APPROVED,
            'total' => 1000,
        ]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_INVOICED,
            'total' => 1200,
        ]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_REJECTED,
            'total' => 900,
        ]);

        $response = $this->getJson('/api/v1/reports/quotes?from=2000-01-01&to=2100-01-01');

        $response->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.approved', 2)
            ->assertJsonPath('data.conversion_rate', 66.7);
    }

    public function test_service_calls_report_counts_completed_calls(): void
    {

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'technician_id' => $this->user->id,
            'status' => ServiceCallStatus::CONVERTED_TO_OS->value,
        ]);

        $response = $this->getJson('/api/v1/reports/service-calls');

        $response->assertOk()
            ->assertJsonPath('data.completed', 1);
    }

    public function test_financial_report_and_csv_export(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'description' => 'Parcela OS',
            'amount' => 1000,
            'amount_paid' => 400,
            'due_date' => now()->toDateString(),
            'status' => AccountReceivable::STATUS_OVERDUE,
        ]);

        AccountPayable::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Fornecedor',
            'amount' => 300,
            'amount_paid' => 100,
            'due_date' => now()->toDateString(),
            'status' => AccountPayable::STATUS_PENDING,
        ]);

        $category = ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Viagem',
            'color' => '#999999',
        ]);

        Expense::create([
            'tenant_id' => $this->tenant->id,
            'expense_category_id' => $category->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'description' => 'Combustivel',
            'amount' => 80,
            'expense_date' => now()->toDateString(),
            'status' => ExpenseStatus::APPROVED,
        ]);

        $report = $this->getJson('/api/v1/reports/financial?from=2000-01-01&to=2100-01-01');
        $report->assertOk();
        $this->assertSame(1, (int) $report->json('data.receivable.count'));
        $this->assertSame(1, (int) $report->json('data.payable.count'));

        $export = $this->get('/api/v1/reports/financial/export');
        $export->assertOk();
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
        $this->assertStringContainsString('section', $export->streamedContent());
    }

    public function test_financial_report_monthly_flow_includes_expenses(): void
    {
        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Receita',
            'amount' => 1000,
            'amount_paid' => 600,
            'due_date' => now()->toDateString(),
            'status' => AccountReceivable::STATUS_PARTIAL,
        ]);

        AccountPayable::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Custo fixo',
            'amount' => 500,
            'amount_paid' => 200,
            'due_date' => now()->toDateString(),
            'status' => AccountPayable::STATUS_PARTIAL,
        ]);

        Expense::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Despesa operacional',
            'amount' => 50,
            'expense_date' => now()->toDateString(),
            'status' => ExpenseStatus::APPROVED,
        ]);

        $response = $this->getJson('/api/v1/reports/financial?from=2000-01-01&to=2100-01-01');
        $response->assertOk();

        $month = now()->format('Y-m');
        $row = collect($response->json('data.monthly_flow'))->firstWhere('period', $month);

        $this->assertNotNull($row);
        $this->assertSame(600.0, (float) ($row['income'] ?? 0));
        $this->assertSame(250.0, (float) ($row['expense'] ?? 0));
        $this->assertSame(350.0, (float) ($row['balance'] ?? 0));
    }

    public function test_financial_reports_accept_os_number_filter(): void
    {
        $workOrderA = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'BLOCO-OS-1001',
            'number' => 'OS-000100',
            'total' => 1000,
            'completed_at' => now(),
        ]);

        $workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'BLOCO-OS-2002',
            'number' => 'OS-000200',
            'total' => 1200,
            'completed_at' => now(),
        ]);

        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $workOrderA->id,
            'created_by' => $this->user->id,
            'description' => 'Receita A',
            'amount' => 1000,
            'amount_paid' => 600,
            'due_date' => now()->toDateString(),
            'status' => AccountReceivable::STATUS_PARTIAL,
        ]);

        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $workOrderB->id,
            'created_by' => $this->user->id,
            'description' => 'Receita B',
            'amount' => 1200,
            'amount_paid' => 900,
            'due_date' => now()->toDateString(),
            'status' => AccountReceivable::STATUS_PARTIAL,
        ]);

        Expense::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrderA->id,
            'created_by' => $this->user->id,
            'description' => 'Despesa A',
            'amount' => 80,
            'expense_date' => now()->toDateString(),
            'status' => ExpenseStatus::APPROVED,
        ]);

        Expense::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrderB->id,
            'created_by' => $this->user->id,
            'description' => 'Despesa B',
            'amount' => 120,
            'expense_date' => now()->toDateString(),
            'status' => ExpenseStatus::APPROVED,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra filtro',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
            'active' => true,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrderA->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrderB->id,
            'user_id' => $this->user->id,
            'base_amount' => 1200,
            'commission_amount' => 120,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $financial = $this->getJson('/api/v1/reports/financial?from=2000-01-01&to=2100-01-01&os_number=BLOCO-OS-1001');
        $financial->assertOk()
            ->assertJsonPath('data.period.os_number', 'BLOCO-OS-1001')
            ->assertJsonPath('data.receivable.count', 1);

        $row = collect($financial->json('data.monthly_flow'))->firstWhere('period', now()->format('Y-m'));
        $this->assertNotNull($row);
        $this->assertSame(600.0, (float) ($row['income'] ?? 0));
        $this->assertSame(80.0, (float) ($row['expense'] ?? 0));

        $commissions = $this->getJson('/api/v1/reports/commissions?from=2000-01-01&to=2100-01-01&os_number=BLOCO-OS-1001');
        $commissions->assertOk()
            ->assertJsonPath('data.period.os_number', 'BLOCO-OS-1001');
        $this->assertSame(100.0, (float) ($commissions->json('data.by_status.0.total') ?? 0));

        $profitability = $this->getJson('/api/v1/reports/profitability?from=2000-01-01&to=2100-01-01&os_number=BLOCO-OS-1001');
        $profitability->assertOk()
            ->assertJsonPath('data.period.os_number', 'BLOCO-OS-1001');

        $this->assertSame(600.0, (float) ($profitability->json('data.revenue') ?? 0));
        $this->assertSame(80.0, (float) ($profitability->json('data.expenses') ?? 0));
        $this->assertSame(100.0, (float) ($profitability->json('data.commissions') ?? 0));
    }

    public function test_commissions_report_by_technician_ignores_reversed_and_cancelled_in_total(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'completed_at' => now(),
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra relatorio comissao',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
            'active' => true,
        ]);

        foreach ([
            [CommissionEvent::STATUS_PENDING, 10],
            [CommissionEvent::STATUS_APPROVED, 20],
            [CommissionEvent::STATUS_PAID, 30],
            [CommissionEvent::STATUS_REVERSED, 40],
            [CommissionEvent::STATUS_CANCELLED, 50],
        ] as [$status, $amount]) {
            CommissionEvent::create([
                'tenant_id' => $this->tenant->id,
                'commission_rule_id' => $rule->id,
                'work_order_id' => $workOrder->id,
                'user_id' => $this->user->id,
                'base_amount' => 1000,
                'commission_amount' => $amount,
                'status' => $status,
            ]);
        }

        $response = $this->getJson('/api/v1/reports/commissions?from=2000-01-01&to=2100-01-01');

        $response->assertOk();
        $this->assertSame(60.0, (float) $response->json('data.by_technician.0.total_commission'));
        $this->assertSame(10.0, (float) $response->json('data.by_technician.0.pending'));
        $this->assertSame(30.0, (float) $response->json('data.by_technician.0.paid'));
    }

    public function test_financial_and_profitability_reports_use_payment_date_for_cash_basis(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'CAIXA-001',
            'completed_at' => now(),
        ]);

        $receivable = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'description' => 'Recebivel mes anterior',
            'amount' => 1000,
            'amount_paid' => 0,
            'due_date' => now()->subMonth()->toDateString(),
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $payable = AccountPayable::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Custo mes anterior CAIXA-001',
            'amount' => 400,
            'amount_paid' => 0,
            'due_date' => now()->subMonth()->toDateString(),
            'status' => AccountPayable::STATUS_PENDING,
            'notes' => 'OS CAIXA-001',
        ]);

        $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 600,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        $this->postJson("/api/v1/accounts-payable/{$payable->id}/pay", [
            'amount' => 250,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $financial = $this->getJson("/api/v1/reports/financial?from={$from}&to={$to}&os_number=CAIXA-001");
        $financial->assertOk();

        $row = collect($financial->json('data.monthly_flow'))->firstWhere('period', now()->format('Y-m'));
        $this->assertNotNull($row);
        $this->assertSame(600.0, (float) ($row['income'] ?? 0));
        $this->assertSame(250.0, (float) ($row['expense'] ?? 0));

        $profitability = $this->getJson("/api/v1/reports/profitability?from={$from}&to={$to}&os_number=CAIXA-001");
        $profitability->assertOk();
        $this->assertSame('600.00', (string) $profitability->json('data.revenue'));
        $this->assertSame('250.00', (string) $profitability->json('data.costs'));
    }

    public function test_crm_and_equipment_reports_return_frontend_compatible_fields(): void
    {
        $pipeline = CrmPipeline::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_default' => true,
        ]);
        $stage = CrmPipelineStage::factory()->create([
            'pipeline_id' => $pipeline->id,
            'probability' => 100,
            'is_won' => true,
        ]);

        $seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_seller_id' => $seller->id,
            'health_score' => 85,
        ]);

        CrmDeal::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'assigned_to' => $seller->id,
            'title' => 'Renovacao',
            'value' => 2500,
            'probability' => 100,
            'source' => 'indicacao',
            'status' => CrmDeal::STATUS_WON,
            'won_at' => now(),
        ]);

        Equipment::factory()->calibrationDue()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        Equipment::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $crm = $this->getJson('/api/v1/reports/crm');
        $crm->assertOk()
            ->assertJsonPath('data.deals_by_seller.0.owner_id', $seller->id)
            ->assertJsonPath('data.deals_by_seller.0.owner_name', $seller->name);
        $this->assertSame(2500.0, (float) $crm->json('data.revenue'));
        $this->assertCount(3, $crm->json('data.health_distribution'));

        $equipments = $this->getJson('/api/v1/reports/equipments');
        $equipments->assertOk()
            ->assertJsonStructure(['data' => [
                'total_active',
                'total_inactive',
                'overdue_calibrations',
                'total_calibration_cost',
                'due_alerts',
            ]]);
        $this->assertGreaterThanOrEqual(1, count($equipments->json('data.due_alerts')));
    }

    public function test_report_export_accepts_legacy_tab_alias(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
            'total' => 999,
        ]);

        $response = $this->get('/api/v1/reports/os/export');

        $response->assertOk();
        $this->assertStringContainsString('section', $response->streamedContent());
    }

    public function test_suppliers_report_returns_ranking_and_categories(): void
    {
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        AccountPayable::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'supplier_id' => $supplier->id,
            'description' => 'Compra material',
            'amount' => 500,
            'amount_paid' => 200,
            'due_date' => now()->toDateString(),
            'status' => AccountPayable::STATUS_PENDING,
        ]);

        $response = $this->getJson('/api/v1/reports/suppliers?from=2000-01-01&to=2100-01-01');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['ranking', 'by_category', 'total_suppliers', 'active_suppliers']])
            ->assertJsonPath('data.total_suppliers', 1)
            ->assertJsonPath('data.active_suppliers', 1);
        $this->assertSame(1, count($response->json('data.ranking')));
    }

    public function test_stock_report_returns_products_and_summary(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sensor Carga',
            'code' => 'SENS-001',
            'stock_qty' => 10,
            'stock_min' => 5,
            'cost_price' => 100,
            'sell_price' => 200,
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabo Balança',
            'code' => 'CAB-001',
            'stock_qty' => 0,
            'stock_min' => 2,
            'cost_price' => 30,
            'sell_price' => 60,
        ]);

        Model::unguard();
        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Depósito Principal',
        ]);
        Model::reguard();

        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'quantity' => 10,
            'reference' => 'Compra inicial',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/reports/stock');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'summary' => ['total_products', 'out_of_stock', 'low_stock', 'total_cost_value', 'total_sale_value'],
                'products',
                'recent_movements' => [['id', 'product_name', 'quantity', 'type', 'movement_type', 'reference', 'created_at']],
            ]])
            ->assertJsonPath('data.summary.total_products', 2)
            ->assertJsonPath('data.summary.out_of_stock', 1);

        $movements = $response->json('data.recent_movements');
        $this->assertCount(1, $movements);
        $this->assertSame('in', $movements[0]['type']);
        $this->assertSame('entry', $movements[0]['movement_type']);
    }

    public function test_technician_cash_report_returns_fund_data(): void
    {
        $fund = TechnicianCashFund::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'balance' => 500,
        ]);

        TechnicianCashTransaction::create([
            'tenant_id' => $this->tenant->id,
            'fund_id' => $fund->id,
            'type' => TechnicianCashTransaction::TYPE_CREDIT,
            'amount' => 500,
            'balance_after' => 500,
            'created_by' => $this->user->id,
            'description' => 'Recarga',
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/reports/technician-cash?from=2000-01-01&to=2100-01-01');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['funds', 'total_balance', 'total_credits', 'total_debits']]);
        $this->assertSame(500.0, (float) $response->json('data.total_balance'));
        $this->assertSame(500.0, (float) $response->json('data.total_credits'));
    }

    public function test_csv_export_works_for_all_report_types(): void
    {
        // Dados mínimos para cada relatório ter algo
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
            'total' => 100,
        ]);

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'technician_id' => $this->user->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);

        Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Export',
            'code' => 'EXP-001',
            'stock_qty' => 5,
            'stock_min' => 2,
            'cost_price' => 50,
            'sell_price' => 100,
        ]);

        $types = [
            'work-orders',
            'productivity',
            'commissions',
            'profitability',
            'quotes',
            'service-calls',
            'technician-cash',
            'crm',
            'equipments',
            'suppliers',
            'stock',
            'customers',
        ];

        foreach ($types as $type) {
            $response = $this->get("/api/v1/reports/{$type}/export?from=2000-01-01&to=2100-01-01");

            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 404]),
                "Export para '{$type}' retornou status inesperado: {$response->getStatusCode()}"
            );

            if ($response->getStatusCode() === 200) {
                $this->assertStringContainsString(
                    'text/csv',
                    (string) $response->headers->get('content-type'),
                    "Export '{$type}' deveria retornar content-type text/csv"
                );
            }
        }
    }

    public function test_profitability_csv_export_returns_expected_revenue_and_margins(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'description' => 'TEST_REVENUE',
            'amount' => 1000,
            'amount_paid' => 1000,
            'due_date' => now()->toDateString(),
            'paid_at' => now()->toDateString(),
            'status' => AccountReceivable::STATUS_PAID,
        ]);

        AccountPayable::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'TEST_COST',
            'amount' => 300,
            'amount_paid' => 300,
            'due_date' => now()->toDateString(),
            'paid_at' => now()->toDateString(),
            'status' => 'paid',
        ]);

        Expense::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'TEST_EXPENSE',
            'amount' => 100,
            'expense_date' => now()->toDateString(),
            'status' => ExpenseStatus::APPROVED,
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $response = $this->get("/api/v1/reports/profitability/export?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();

        // 1000 revenue - 300 cost - 100 expense = 600 net profit
        $this->assertStringContainsString('Receitas (+)', $content);
        $this->assertStringContainsString('Custos Diretos (AP) (-)', $content);
        $this->assertStringContainsString('Despesas (-)', $content);
        $this->assertStringContainsString('Lucro Liquido', $content);
        $this->assertStringContainsString('Margem de Lucro (%)', $content);
        $this->assertStringContainsString('1000,00', $content); // Revenue
        $this->assertStringContainsString('300,00', $content); // Cost
        $this->assertStringContainsString('100,00', $content); // Expense
        $this->assertStringContainsString('600,00', $content); // Net Profit
    }
}
