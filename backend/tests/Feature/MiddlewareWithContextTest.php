<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckReportExportPermission;
use App\Models\CommissionEvent;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Middleware Full Coverage Test — validates that ALL major endpoints
 * correctly enforce permission middleware in production conditions.
 *
 * Unlike most tests that use withoutMiddleware(), these run WITH real
 * middleware to guarantee security is actually enforced.
 */
class MiddlewareWithContextTest extends TestCase
{
    private Tenant $tenant;

    private User $userWithoutPerms;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        // Re-habilita middlewares de permissão que o TestCase base desabilita
        // pois este teste valida que endpoints EXIGEM permissões
        $this->withMiddleware([
            CheckPermission::class,
            CheckReportExportPermission::class,
        ]);
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();

        // User without any permissions — should be blocked everywhere
        $this->userWithoutPerms = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->userWithoutPerms->tenants()->attach($this->tenant->id, ['is_default' => true]);

        // Super admin with all permissions
        $this->superAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->superAdmin->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
    }

    /**
     * Helper: grant specific permissions to the user without permissions.
     */
    private function grant(string ...$permissionNames): void
    {
        foreach ($permissionNames as $name) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
        }
        $this->userWithoutPerms->givePermissionTo($permissionNames);
        $this->userWithoutPerms->unsetRelation('permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Helper: act as the user without permissions.
     */
    private function actAsRestricted(): self
    {
        Sanctum::actingAs($this->userWithoutPerms, ['*']);

        return $this;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CUSTOMERS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_customers_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/customers')->assertForbidden();

        $this->grant('cadastros.customer.view');
        $this->getJson('/api/v1/customers')->assertOk();
    }

    public function test_customers_create_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->postJson('/api/v1/customers', ['name' => 'Test'])->assertForbidden();

        $this->grant('cadastros.customer.create');
        $this->postJson('/api/v1/customers', [
            'name' => 'Test', 'type' => 'PF',
        ])->assertStatus(201);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PRODUCTS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_products_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/products')->assertForbidden();

        $this->grant('cadastros.product.view');
        $this->getJson('/api/v1/products')->assertOk();
    }

    public function test_products_create_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->postJson('/api/v1/products', ['name' => 'Test'])->assertForbidden();

        $this->grant('cadastros.product.create');
        $response = $this->postJson('/api/v1/products', ['name' => 'Test Product']);
        $this->assertNotEquals(403, $response->status());
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SERVICES MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_services_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/services')->assertForbidden();

        $this->grant('cadastros.service.view');
        $this->getJson('/api/v1/services')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SUPPLIERS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_suppliers_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/suppliers')->assertForbidden();

        $this->grant('cadastros.supplier.view');
        $this->getJson('/api/v1/suppliers')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  EQUIPMENTS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_equipments_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/equipments')->assertForbidden();

        $this->grant('equipments.equipment.view');
        $this->getJson('/api/v1/equipments')->assertOk();
    }

    public function test_equipments_create_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->postJson('/api/v1/equipments', ['name' => 'Balança'])->assertForbidden();

        $this->grant('equipments.equipment.create');
        $response = $this->postJson('/api/v1/equipments', ['name' => 'Balança']);
        $this->assertNotEquals(403, $response->status());
    }

    public function test_equipments_dashboard_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/equipments-dashboard')->assertForbidden();

        $this->grant('equipments.equipment.view');
        $this->getJson('/api/v1/equipments-dashboard')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STANDARD WEIGHTS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_standard_weights_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/standard-weights')->assertForbidden();

        $this->grant('equipments.standard_weight.view');
        $this->getJson('/api/v1/standard-weights')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  WORK ORDERS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_work_orders_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/work-orders')->assertForbidden();

        $this->grant('os.work_order.view');
        $this->getJson('/api/v1/work-orders')->assertOk();
    }

    public function test_work_orders_create_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->postJson('/api/v1/work-orders', ['description' => 'OS Teste'])->assertForbidden();

        $this->grant('os.work_order.create');
        $response = $this->postJson('/api/v1/work-orders', ['description' => 'OS Teste']);
        $this->assertNotEquals(403, $response->status());
    }

    // ═══════════════════════════════════════════════════════════════════
    //  QUOTES MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_quotes_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/quotes')->assertForbidden();

        $this->grant('quotes.quote.view');
        $this->getJson('/api/v1/quotes')->assertOk();
    }

    public function test_quotes_create_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->postJson('/api/v1/quotes', ['title' => 'Quote'])->assertForbidden();

        $this->grant('quotes.quote.create');
        $response = $this->postJson('/api/v1/quotes', ['title' => 'Quote']);
        $this->assertNotEquals(403, $response->status());
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SERVICE CALLS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_service_calls_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/service-calls')->assertForbidden();

        $this->grant('service_calls.service_call.view');
        $this->getJson('/api/v1/service-calls')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CRM MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_crm_deals_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/crm/deals')->assertForbidden();

        $this->grant('crm.deal.view');
        $this->getJson('/api/v1/crm/deals')->assertOk();
    }

    public function test_crm_pipelines_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/crm/pipelines')->assertForbidden();

        $this->grant('crm.pipeline.view');
        $this->getJson('/api/v1/crm/pipelines')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  INMETRO MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_inmetro_dashboard_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/inmetro/dashboard')->assertForbidden();

        $this->grant('inmetro.intelligence.view');
        $response = $this->getJson('/api/v1/inmetro/dashboard');
        $this->assertNotEquals(403, $response->status());
    }

    // ═══════════════════════════════════════════════════════════════════
    //  IMPORT MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_import_history_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/import/history')->assertForbidden();

        $this->grant('import.data.view');
        $this->getJson('/api/v1/import/history')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STOCK MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_stock_movements_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/stock/movements')->assertForbidden();

        $this->grant('estoque.movement.view');
        $this->getJson('/api/v1/stock/movements')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  REPORTS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_report_work_orders_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/reports/work-orders')->assertForbidden();

        $this->grant('reports.os_report.view');
        $this->getJson('/api/v1/reports/work-orders')->assertOk();
    }

    public function test_report_financial_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/reports/financial')->assertForbidden();

        $this->grant('reports.financial_report.view');
        $this->getJson('/api/v1/reports/financial')->assertOk();
    }

    public function test_report_productivity_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/reports/productivity')->assertForbidden();

        $this->grant('reports.productivity_report.view');
        $this->getJson('/api/v1/reports/productivity')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SETTINGS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_settings_view_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/settings')->assertForbidden();

        $this->grant('platform.settings.view');
        $this->getJson('/api/v1/settings')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  BRANCHES MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_branches_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/branches')->assertForbidden();

        $this->grant('platform.branch.view');
        $this->getJson('/api/v1/branches')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  TENANTS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_tenants_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/tenants')->assertForbidden();

        $this->grant('platform.tenant.view');
        $this->getJson('/api/v1/tenants')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  EXPENSES MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_expenses_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/expenses')->assertForbidden();

        $this->grant('expenses.expense.view');
        $this->getJson('/api/v1/expenses')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  COMMISSIONS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_commission_rules_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/commission-rules')->assertForbidden();

        $this->grant('commissions.rule.view');
        $this->getJson('/api/v1/commission-rules')->assertOk();
    }

    public function test_commission_users_requires_commission_view_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/commission-users')->assertForbidden();

        $this->grant('commissions.rule.view');
        $this->getJson('/api/v1/commission-users')->assertOk();
    }

    public function test_commission_event_splits_read_requires_view_permission_not_update(): void
    {
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actAsRestricted();
        $this->getJson("/api/v1/commission-events/{$event->id}/splits")->assertForbidden();

        $this->grant('commissions.event.view');
        $this->getJson("/api/v1/commission-events/{$event->id}/splits")->assertOk();
    }

    public function test_technician_cash_admin_routes_accept_manage_permission_without_view(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/technician-cash')->assertForbidden();
        $this->getJson('/api/v1/technician-cash-summary')->assertForbidden();

        $this->grant('technicians.cashbox.manage');

        $this->getJson('/api/v1/technician-cash')->assertOk();
        $this->getJson('/api/v1/technician-cash-summary')->assertOk();
    }

    public function test_technicians_options_accepts_fund_transfer_create_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web']);
        $technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $technician->assignRole($role);

        $this->actAsRestricted();
        $this->getJson('/api/v1/technicians/options')->assertForbidden();

        $this->grant('financial.fund_transfer.create');
        $this->getJson('/api/v1/technicians/options')
            ->assertOk()
            ->assertJsonFragment(['id' => $technician->id, 'name' => $technician->name]);
    }

    public function test_bank_account_and_payment_method_lookups_accept_fund_transfer_create_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/bank-accounts')->assertForbidden();
        $this->getJson('/api/v1/payment-methods')->assertForbidden();

        $this->grant('financial.fund_transfer.create');

        $this->getJson('/api/v1/bank-accounts')->assertOk();
        $this->getJson('/api/v1/payment-methods')->assertOk();
    }

    public function test_financial_create_permissions_can_access_scoped_lookup_routes(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Financeiro',
        ]);
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor Financeiro',
            'is_active' => true,
        ]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->actAsRestricted();
        $this->getJson('/api/v1/financial/lookups/customers')->assertForbidden();
        $this->getJson('/api/v1/financial/lookups/suppliers')->assertForbidden();
        $this->getJson('/api/v1/financial/lookups/work-orders')->assertForbidden();
        $this->getJson('/api/v1/financial/lookups/bank-accounts')->assertForbidden();

        $this->grant('finance.receivable.create', 'finance.payable.create', 'expenses.expense.create');

        $this->getJson('/api/v1/financial/lookups/customers')
            ->assertOk()
            ->assertJsonFragment(['id' => $customer->id, 'name' => $customer->name]);

        $this->getJson('/api/v1/financial/lookups/suppliers')
            ->assertOk()
            ->assertJsonFragment(['id' => $supplier->id, 'name' => $supplier->name]);

        $this->getJson('/api/v1/financial/lookups/work-orders')
            ->assertOk()
            ->assertJsonFragment(['id' => $workOrder->id]);

        $this->getJson('/api/v1/financial/lookups/bank-accounts')
            ->assertOk();
    }

    public function test_financial_payment_method_lookup_accepts_operational_permissions(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/financial/lookups/payment-methods')->assertForbidden();

        $this->grant('finance.payable.settle');
        $this->getJson('/api/v1/financial/lookups/payment-methods')->assertOk();
    }

    public function test_financial_payable_permissions_can_access_advanced_supplier_routes(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/financial/supplier-contracts')->assertForbidden();
        $this->getJson('/api/v1/financial/supplier-advances')->assertForbidden();
        $this->postJson('/api/v1/financial/supplier-advances', [])->assertForbidden();

        $this->grant('finance.payable.view', 'finance.payable.create');

        $this->getJson('/api/v1/financial/supplier-contracts')->assertOk();
        $this->getJson('/api/v1/financial/supplier-advances')->assertOk();
        $this->getJson('/api/v1/financial/lookups/supplier-contract-payment-frequencies')->assertOk();
        $this->postJson('/api/v1/financial/supplier-advances', [])->assertStatus(422);
    }

    public function test_financial_receivable_permissions_can_access_collection_and_simulator_routes(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/financial/collection-rules')->assertForbidden();
        $this->postJson('/api/v1/financial/receivables-simulator', [
            'monthly_rate' => 2,
        ])->assertForbidden();

        $this->grant('finance.receivable.view');

        $this->getJson('/api/v1/financial/collection-rules')->assertOk();
        $this->postJson('/api/v1/financial/receivables-simulator', [
            'monthly_rate' => 2,
        ])->assertOk();
    }

    public function test_financial_dre_permission_can_access_tax_calculation_route(): void
    {
        $this->actAsRestricted();
        $this->postJson('/api/v1/financial/tax-calculation', [
            'gross_amount' => 1000,
            'service_type' => 'consultoria',
            'tax_regime' => 'simples_nacional',
        ])->assertForbidden();

        $this->grant('finance.dre.view');

        $this->postJson('/api/v1/financial/tax-calculation', [
            'gross_amount' => 1000,
            'service_type' => 'consultoria',
            'tax_regime' => 'simples_nacional',
        ])->assertOk();
    }

    public function test_expense_permissions_can_access_reimbursements_routes(): void
    {
        // postJson('') previously used empty string (bug: resolves to POST / which returns 405).
        // Corrected to the actual endpoint URL.
        $this->actAsRestricted();
        $this->getJson('/api/v1/financial/expense-reimbursements')->assertForbidden();
        $this->postJson('/api/v1/financial/expense-reimbursements', ['approval_channel' => 'whatsapp', 'terms_accepted' => true])->assertForbidden();

        $this->grant('expenses.expense.view');
        $this->getJson('/api/v1/financial/expense-reimbursements')->assertOk();

        $this->grant('expenses.expense.approve');
        // With permission granted but missing required 'amount' field, validation returns 422.
        $this->postJson('/api/v1/financial/expense-reimbursements', ['approval_channel' => 'whatsapp', 'terms_accepted' => true])->assertStatus(422);
    }

    public function test_financial_analytics_routes_accept_modern_permissions(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/financial/cash-flow-weekly')->assertForbidden();
        $this->getJson('/api/v1/financial/dre')->assertForbidden();
        $this->getJson('/api/v1/financial/aging-report')->assertForbidden();
        $this->getJson('/api/v1/financial/expense-allocation')->assertForbidden();
        $this->getJson('/api/v1/financial/consolidated')->assertForbidden();

        $this->grant('finance.cashflow.view', 'finance.dre.view', 'finance.receivable.view', 'expenses.expense.view');

        $this->getJson('/api/v1/financial/cash-flow-weekly')->assertOk();
        $this->getJson('/api/v1/financial/dre')->assertOk();
        $this->getJson('/api/v1/financial/aging-report')->assertOk();
        $this->getJson('/api/v1/financial/expense-allocation')->assertOk();
        $this->getJson('/api/v1/financial/consolidated')->assertOk();
    }

    public function test_batch_payment_routes_follow_payable_permissions(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/financial/batch-payment-approval')->assertForbidden();
        $this->postJson('/api/v1/financial/batch-payment-approval', [
            'ids' => [],
            'payment_method' => 'pix',
        ])->assertForbidden();

        $this->grant('finance.payable.view');
        $this->getJson('/api/v1/financial/batch-payment-approval')->assertOk();

        $this->grant('finance.payable.settle');
        $this->postJson('/api/v1/financial/batch-payment-approval', [
            'ids' => [],
            'payment_method' => 'pix',
        ])->assertStatus(422);
    }

    public function test_financial_export_routes_follow_matching_view_permission_by_type(): void
    {
        $query = '?from=2000-01-01&to=2100-01-01';

        $this->actAsRestricted();
        $this->getJson("/api/v1/financial/export/csv{$query}&type=payable")->assertForbidden();
        $this->getJson("/api/v1/financial/export/csv{$query}&type=receivable")->assertForbidden();

        $this->grant('finance.payable.view');
        $this->getJson("/api/v1/financial/export/csv{$query}&type=payable")->assertOk();
        $this->getJson("/api/v1/financial/export/csv{$query}&type=receivable")->assertForbidden();

        $this->userWithoutPerms->syncPermissions([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->grant('finance.receivable.view');
        $this->getJson("/api/v1/financial/export/ofx{$query}&type=receivable")->assertOk();
        $this->getJson("/api/v1/financial/export/ofx{$query}&type=payable")->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CENTRAL MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_central_items_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/agenda/items')->assertForbidden();

        $this->grant('agenda.item.view');
        $this->getJson('/api/v1/agenda/items')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  FISCAL MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_fiscal_notas_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/fiscal/notas')->assertForbidden();

        $this->grant('fiscal.note.view');
        $this->getJson('/api/v1/fiscal/notas')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  NOTIFICATIONS MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_notifications_list_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/notifications')->assertForbidden();

        $this->grant('notifications.notification.view');
        $this->getJson('/api/v1/notifications')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  DASHBOARD MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_dashboard_stats_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/dashboard-stats')->assertForbidden();

        $this->grant('platform.dashboard.view');
        $this->getJson('/api/v1/dashboard-stats')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CASH FLOW / DRE MODULE
    // ═══════════════════════════════════════════════════════════════════

    public function test_cash_flow_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/cash-flow')->assertForbidden();

        $this->grant('finance.cashflow.view');
        $this->getJson('/api/v1/cash-flow')->assertOk();
    }

    public function test_dre_requires_permission(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/dre')->assertForbidden();

        $this->grant('finance.dre.view');
        $this->getJson('/api/v1/dre')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CROSS-TENANT ISOLATION
    // ═══════════════════════════════════════════════════════════════════

    public function test_user_cannot_access_other_tenant_data(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);

        setPermissionsTeamId($otherTenant->id);
        $perm = Permission::firstOrCreate(['name' => 'cadastros.customer.view', 'guard_name' => 'web']);
        $otherUser->givePermissionTo($perm);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Sanctum::actingAs($otherUser, ['*']);
        app()->instance('current_tenant_id', $otherTenant->id);

        // Create customer in original tenant
        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Secret Customer',
            'type' => 'PF',
        ]);

        // Other tenant user should NOT see it
        $response = $this->getJson('/api/v1/customers');
        $response->assertOk();
        $response->assertJsonMissing(['name' => 'Secret Customer']);
    }

    public function test_bank_reconciliation_routes_accept_matching_receivable_or_payable_permissions(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/bank-reconciliation/summary')->assertForbidden();
        $this->postJson('/api/v1/bank-reconciliation/import')->assertForbidden();
        $this->deleteJson('/api/v1/bank-reconciliation/statements/1')->assertForbidden();

        $this->grant('finance.payable.view', 'finance.payable.create', 'finance.payable.delete');

        $this->getJson('/api/v1/bank-reconciliation/summary')->assertOk();
        $this->postJson('/api/v1/bank-reconciliation/import')->assertStatus(422);
        $this->deleteJson('/api/v1/bank-reconciliation/statements/1')->assertStatus(404);
    }

    public function test_reconciliation_rules_and_bank_account_lookup_accept_payable_permissions(): void
    {
        $this->actAsRestricted();
        $this->getJson('/api/v1/reconciliation-rules')->assertForbidden();
        $this->getJson('/api/v1/financial/lookups/bank-accounts')->assertForbidden();

        $this->grant('finance.payable.view', 'finance.payable.create');

        $this->getJson('/api/v1/reconciliation-rules')->assertOk();
        $this->getJson('/api/v1/financial/lookups/bank-accounts')->assertOk();
    }
}
