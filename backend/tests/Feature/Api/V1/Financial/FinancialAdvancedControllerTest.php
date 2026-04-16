<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Enums\ExpenseStatus;
use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialCheck;
use App\Models\Supplier;
use App\Models\SupplierContract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FinancialAdvancedControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1. SUPPLIER CONTRACTS
    // ═══════════════════════════════════════════════════════════════════

    public function test_list_supplier_contracts(): void
    {
        SupplierContract::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'description' => 'Contrato de manutenção',
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'value' => '5000.00',
            'payment_frequency' => 'monthly',
            'auto_renew' => false,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/financial/supplier-contracts');
        $response->assertOk();
    }

    public function test_store_supplier_contract(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/financial/supplier-contracts', [
            'supplier_id' => $this->supplier->id,
            'description' => 'Contrato novo',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'value' => '12000.00',
            'payment_frequency' => 'monthly',
            'auto_renew' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('supplier_contracts', [
            'tenant_id' => $this->tenant->id,
            'description' => 'Contrato novo',
            'status' => 'active',
        ]);
    }

    public function test_update_supplier_contract(): void
    {
        $contract = SupplierContract::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'description' => 'Original',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'value' => '3000.00',
            'payment_frequency' => 'monthly',
            'auto_renew' => false,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/financial/supplier-contracts/{$contract->id}", [
            'supplier_id' => $this->supplier->id,
            'description' => 'Atualizado',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'value' => '4500.00',
            'payment_frequency' => 'monthly',
        ]);

        $response->assertOk();
    }

    public function test_destroy_supplier_contract(): void
    {
        $contract = SupplierContract::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'description' => 'Para excluir',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'value' => '1000.00',
            'payment_frequency' => 'monthly',
            'auto_renew' => false,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/financial/supplier-contracts/{$contract->id}");
        $response->assertOk();
    }

    public function test_destroy_supplier_contract_not_found(): void
    {
        $response = $this->actingAs($this->user)->deleteJson('/api/v1/financial/supplier-contracts/99999');
        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. TAX CALCULATION
    // ═══════════════════════════════════════════════════════════════════

    public function test_tax_calculation_simples_nacional(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/financial/tax-calculation', [
            'gross_amount' => 10000,
            'service_type' => 'service',
            'tax_regime' => 'simples_nacional',
        ]);

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();
        $this->assertArrayHasKey('taxes', $data);
        $this->assertArrayHasKey('total_tax', $data);
        $this->assertArrayHasKey('net_amount', $data);
    }

    public function test_tax_calculation_lucro_presumido(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/financial/tax-calculation', [
            'gross_amount' => 50000,
            'service_type' => 'service',
            'tax_regime' => 'lucro_presumido',
        ]);

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();
        $this->assertNotEmpty($data['taxes']);
    }

    public function test_tax_calculation_lucro_real(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/financial/tax-calculation', [
            'gross_amount' => 100000,
            'service_type' => 'service',
            'tax_regime' => 'lucro_real',
        ]);

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. EXPENSE REIMBURSEMENTS
    // ═══════════════════════════════════════════════════════════════════

    public function test_list_expense_reimbursements(): void
    {
        $category = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $category->id,
            'status' => ExpenseStatus::APPROVED,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/financial/expense-reimbursements');
        $response->assertOk();
    }

    public function test_approve_expense_reimbursement(): void
    {
        $category = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $category->id,
            'status' => ExpenseStatus::APPROVED,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/financial/expense-reimbursements/{$expense->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);
        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. CHECK MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════

    public function test_list_financial_checks(): void
    {
        FinancialCheck::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'received',
            'number' => 'CHK-001',
            'bank' => 'Banco do Brasil',
            'amount' => '1500.00',
            'issuer' => 'Cliente XYZ',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/financial/checks');
        $response->assertOk();
    }

    public function test_store_financial_check(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/financial/checks', [
            'type' => 'received',
            'number' => 'CHK-002',
            'bank' => 'Banco do Brasil',
            'amount' => '2500.00',
            'issuer' => 'Fornecedor ABC',
            'due_date' => now()->addDays(15)->format('Y-m-d'),
        ]);

        $response->assertCreated();
    }

    public function test_update_check_status(): void
    {
        $check = FinancialCheck::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'received',
            'number' => 'CHK-003',
            'bank' => 'Bradesco',
            'amount' => '3000.00',
            'issuer' => 'Test Issuer',
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/v1/financial/checks/{$check->id}/status", [
            'status' => 'deposited',
        ]);

        $response->assertOk();
    }

    public function test_update_check_status_not_found(): void
    {
        $response = $this->actingAs($this->user)->patchJson('/api/v1/financial/checks/99999/status', [
            'status' => 'deposited',
        ]);

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════════
    // 5. RECEIVABLES SIMULATOR
    // ═══════════════════════════════════════════════════════════════════

    public function test_receivables_simulator(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '10000.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::PENDING->value,
            'due_date' => now()->addDays(60)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/financial/receivables-simulator', [
            'monthly_rate' => 2.5,
        ]);

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();
        $this->assertArrayHasKey('summary', $data);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 6. COLLECTION RULES
    // ═══════════════════════════════════════════════════════════════════

    public function test_collection_rules(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '5000.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::PENDING->value,
            'due_date' => now()->subDays(10)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/financial/collection-rules');
        $response->assertOk();
        $data = $response->json('data') ?? $response->json();
        $this->assertArrayHasKey('summary', $data);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 7. SUPPLIER ADVANCES
    // ═══════════════════════════════════════════════════════════════════

    public function test_list_supplier_advances(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => '[Adiantamento] Fornecedor',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/financial/supplier-advances');
        $response->assertOk();
    }

    public function test_store_supplier_advance(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/financial/supplier-advances', [
            'supplier_id' => $this->supplier->id,
            'description' => 'Adiantamento para compra de materiais',
            'amount' => '3500.00',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('accounts_payable', [
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 8. UNAUTHENTICATED ACCESS
    // ═══════════════════════════════════════════════════════════════════

    public function test_unauthenticated_supplier_contracts_returns_401(): void
    {
        $response = $this->getJson('/api/v1/financial/supplier-contracts');
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_checks_returns_401(): void
    {
        $response = $this->getJson('/api/v1/financial/checks');
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_tax_calculation_returns_401(): void
    {
        $response = $this->postJson('/api/v1/financial/tax-calculation', [
            'gross_amount' => 1000,
            'service_type' => 'service',
            'tax_regime' => 'simples_nacional',
        ]);
        $response->assertUnauthorized();
    }
}
