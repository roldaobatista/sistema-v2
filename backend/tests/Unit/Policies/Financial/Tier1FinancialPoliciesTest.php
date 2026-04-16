<?php

namespace Tests\Unit\Policies\Financial;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\DebtRenegotiation;
use App\Models\Expense;
use App\Models\FiscalNote;
use App\Models\FundTransfer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\AccountPayablePolicy;
use App\Policies\AccountReceivablePolicy;
use App\Policies\BankAccountPolicy;
use App\Policies\ChartOfAccountPolicy;
use App\Policies\DebtRenegotiationPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\FiscalNotePolicy;
use App\Policies\FundTransferPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cobertura unitaria das 10 policies financeiras Tier 1.
 *
 * Cada policy recebe 3 testes:
 *  1. admin com permissoes acessa todos os metodos publicos
 *  2. usuario sem permissao e negado em todos os metodos publicos
 *  3. admin e negado em modelos de outro tenant (isolamento)
 *
 * UserPolicy fica em arquivo separado (tests/Unit/Policies/Iam/UserPolicyTest.php)
 * porque usa pivot tenants() em vez de comparacao direta de tenant_id.
 */
class Tier1FinancialPoliciesTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $admin;

    private User $noPerms;

    /** @var array<string> */
    private array $permissions = [
        // finance.*
        'finance.payable.view',
        'finance.payable.create',
        'finance.payable.update',
        'finance.payable.delete',
        'finance.payable.settle',
        'finance.receivable.view',
        'finance.receivable.create',
        'finance.receivable.update',
        'finance.receivable.delete',
        'finance.receivable.settle',
        'finance.chart.view',
        'finance.chart.create',
        'finance.chart.update',
        'finance.chart.delete',
        // financial.*
        'financial.bank_account.view',
        'financial.bank_account.create',
        'financial.bank_account.update',
        'financial.bank_account.delete',
        'financial.fund_transfer.view',
        'financial.fund_transfer.create',
        'financial.fund_transfer.cancel',
        // financeiro.*
        'financeiro.renegotiation.view',
        'financeiro.renegotiation.create',
        'financeiro.renegotiation.approve',
        // expenses.*
        'expenses.expense.view',
        'expenses.expense.create',
        'expenses.expense.update',
        'expenses.expense.delete',
        'expenses.expense.approve',
        'expenses.expense.review',
        // fiscal.*
        'fiscal.note.view',
        'fiscal.note.create',
        'fiscal.note.cancel',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        foreach ($this->permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->setTenantContext($this->tenant->id);

        $adminRole = Role::findByName('admin', 'web');
        $adminRole->givePermissionTo($this->permissions);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->admin->assignRole('admin');

        $this->noPerms = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->noPerms->tenants()->attach($this->tenant->id, ['is_default' => true]);
    }

    /**
     * Cria uma instancia nao-persistida do modelo com tenant_id definido.
     * Policies so leem o atributo tenant_id — nao precisa hit no DB.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function makeForTenant(string $class, int $tenantId): Model
    {
        /** @var Model $model */
        $model = new $class;
        $model->forceFill(['tenant_id' => $tenantId]);

        return $model;
    }

    // ═══════════════════════════════════════════════
    // AccountPayablePolicy
    // ═══════════════════════════════════════════════

    public function test_account_payable_admin_has_full_access(): void
    {
        $policy = new AccountPayablePolicy;
        $model = $this->makeForTenant(AccountPayable::class, $this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $model));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $model));
        $this->assertTrue($policy->delete($this->admin, $model));
    }

    public function test_account_payable_no_permission_denies_all(): void
    {
        $policy = new AccountPayablePolicy;
        $model = $this->makeForTenant(AccountPayable::class, $this->tenant->id);

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $model));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->update($this->noPerms, $model));
        $this->assertFalse($policy->delete($this->noPerms, $model));
    }

    public function test_account_payable_cross_tenant_denies(): void
    {
        $policy = new AccountPayablePolicy;
        $foreign = $this->makeForTenant(AccountPayable::class, $this->otherTenant->id);

        $this->assertFalse($policy->view($this->admin, $foreign));
        $this->assertFalse($policy->update($this->admin, $foreign));
        $this->assertFalse($policy->delete($this->admin, $foreign));
    }

    // ═══════════════════════════════════════════════
    // AccountReceivablePolicy
    // ═══════════════════════════════════════════════

    public function test_account_receivable_admin_has_full_access(): void
    {
        $policy = new AccountReceivablePolicy;
        $model = $this->makeForTenant(AccountReceivable::class, $this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $model));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $model));
        $this->assertTrue($policy->delete($this->admin, $model));
    }

    public function test_account_receivable_no_permission_denies_all(): void
    {
        $policy = new AccountReceivablePolicy;
        $model = $this->makeForTenant(AccountReceivable::class, $this->tenant->id);

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $model));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->update($this->noPerms, $model));
        $this->assertFalse($policy->delete($this->noPerms, $model));
    }

    public function test_account_receivable_cross_tenant_denies(): void
    {
        $policy = new AccountReceivablePolicy;
        $foreign = $this->makeForTenant(AccountReceivable::class, $this->otherTenant->id);

        $this->assertFalse($policy->view($this->admin, $foreign));
        $this->assertFalse($policy->update($this->admin, $foreign));
        $this->assertFalse($policy->delete($this->admin, $foreign));
    }

    // ═══════════════════════════════════════════════
    // BankAccountPolicy
    // ═══════════════════════════════════════════════

    public function test_bank_account_admin_has_full_access(): void
    {
        $policy = new BankAccountPolicy;
        $model = $this->makeForTenant(BankAccount::class, $this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $model));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $model));
        $this->assertTrue($policy->delete($this->admin, $model));
    }

    public function test_bank_account_no_permission_denies_all(): void
    {
        $policy = new BankAccountPolicy;
        $model = $this->makeForTenant(BankAccount::class, $this->tenant->id);

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $model));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->update($this->noPerms, $model));
        $this->assertFalse($policy->delete($this->noPerms, $model));
    }

    public function test_bank_account_cross_tenant_denies(): void
    {
        $policy = new BankAccountPolicy;
        $foreign = $this->makeForTenant(BankAccount::class, $this->otherTenant->id);

        $this->assertFalse($policy->view($this->admin, $foreign));
        $this->assertFalse($policy->update($this->admin, $foreign));
        $this->assertFalse($policy->delete($this->admin, $foreign));
    }

    // ═══════════════════════════════════════════════
    // ChartOfAccountPolicy
    // ═══════════════════════════════════════════════

    public function test_chart_of_account_admin_has_full_access(): void
    {
        $policy = new ChartOfAccountPolicy;
        $model = $this->makeForTenant(ChartOfAccount::class, $this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $model));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $model));
        $this->assertTrue($policy->delete($this->admin, $model));
    }

    public function test_chart_of_account_no_permission_denies_all(): void
    {
        $policy = new ChartOfAccountPolicy;
        $model = $this->makeForTenant(ChartOfAccount::class, $this->tenant->id);

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $model));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->update($this->noPerms, $model));
        $this->assertFalse($policy->delete($this->noPerms, $model));
    }

    public function test_chart_of_account_cross_tenant_denies(): void
    {
        $policy = new ChartOfAccountPolicy;
        $foreign = $this->makeForTenant(ChartOfAccount::class, $this->otherTenant->id);

        $this->assertFalse($policy->view($this->admin, $foreign));
        $this->assertFalse($policy->update($this->admin, $foreign));
        $this->assertFalse($policy->delete($this->admin, $foreign));
    }

    // ═══════════════════════════════════════════════
    // ExpensePolicy (inclui approve + review)
    // ═══════════════════════════════════════════════

    public function test_expense_admin_has_full_access(): void
    {
        $policy = new ExpensePolicy;
        $model = $this->makeForTenant(Expense::class, $this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $model));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $model));
        $this->assertTrue($policy->delete($this->admin, $model));
        $this->assertTrue($policy->approve($this->admin, $model));
        $this->assertTrue($policy->review($this->admin, $model));
    }

    public function test_expense_no_permission_denies_all(): void
    {
        $policy = new ExpensePolicy;
        $model = $this->makeForTenant(Expense::class, $this->tenant->id);

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $model));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->update($this->noPerms, $model));
        $this->assertFalse($policy->delete($this->noPerms, $model));
        $this->assertFalse($policy->approve($this->noPerms, $model));
        $this->assertFalse($policy->review($this->noPerms, $model));
    }

    public function test_expense_cross_tenant_denies(): void
    {
        $policy = new ExpensePolicy;
        $foreign = $this->makeForTenant(Expense::class, $this->otherTenant->id);

        $this->assertFalse($policy->view($this->admin, $foreign));
        $this->assertFalse($policy->update($this->admin, $foreign));
        $this->assertFalse($policy->delete($this->admin, $foreign));
        $this->assertFalse($policy->approve($this->admin, $foreign));
        $this->assertFalse($policy->review($this->admin, $foreign));
    }

    // ═══════════════════════════════════════════════
    // FundTransferPolicy (viewAny, view, create, cancel)
    // ═══════════════════════════════════════════════

    public function test_fund_transfer_admin_has_full_access(): void
    {
        $policy = new FundTransferPolicy;
        $model = $this->makeForTenant(FundTransfer::class, $this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $model));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->cancel($this->admin, $model));
    }

    public function test_fund_transfer_no_permission_denies_all(): void
    {
        $policy = new FundTransferPolicy;
        $model = $this->makeForTenant(FundTransfer::class, $this->tenant->id);

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $model));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->cancel($this->noPerms, $model));
    }

    public function test_fund_transfer_cross_tenant_denies(): void
    {
        $policy = new FundTransferPolicy;
        $foreign = $this->makeForTenant(FundTransfer::class, $this->otherTenant->id);

        $this->assertFalse($policy->view($this->admin, $foreign));
        $this->assertFalse($policy->cancel($this->admin, $foreign));
    }

    // ═══════════════════════════════════════════════
    // DebtRenegotiationPolicy (viewAny, view, create, update — NO delete)
    // ═══════════════════════════════════════════════

    public function test_debt_renegotiation_admin_has_full_access(): void
    {
        $policy = new DebtRenegotiationPolicy;
        $model = $this->makeForTenant(DebtRenegotiation::class, $this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $model));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $model));
    }

    public function test_debt_renegotiation_no_permission_denies_all(): void
    {
        $policy = new DebtRenegotiationPolicy;
        $model = $this->makeForTenant(DebtRenegotiation::class, $this->tenant->id);

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $model));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->update($this->noPerms, $model));
    }

    public function test_debt_renegotiation_cross_tenant_denies(): void
    {
        $policy = new DebtRenegotiationPolicy;
        $foreign = $this->makeForTenant(DebtRenegotiation::class, $this->otherTenant->id);

        $this->assertFalse($policy->view($this->admin, $foreign));
        $this->assertFalse($policy->update($this->admin, $foreign));
    }

    // ═══════════════════════════════════════════════
    // InvoicePolicy
    // ═══════════════════════════════════════════════

    public function test_invoice_admin_has_full_access(): void
    {
        $policy = new InvoicePolicy;
        $model = $this->makeForTenant(Invoice::class, $this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $model));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $model));
        $this->assertTrue($policy->delete($this->admin, $model));
    }

    public function test_invoice_no_permission_denies_all(): void
    {
        $policy = new InvoicePolicy;
        $model = $this->makeForTenant(Invoice::class, $this->tenant->id);

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $model));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->update($this->noPerms, $model));
        $this->assertFalse($policy->delete($this->noPerms, $model));
    }

    public function test_invoice_cross_tenant_denies(): void
    {
        $policy = new InvoicePolicy;
        $foreign = $this->makeForTenant(Invoice::class, $this->otherTenant->id);

        $this->assertFalse($policy->view($this->admin, $foreign));
        $this->assertFalse($policy->update($this->admin, $foreign));
        $this->assertFalse($policy->delete($this->admin, $foreign));
    }

    // ═══════════════════════════════════════════════
    // PaymentPolicy (viewAny, view, create, delete — NO update)
    // ═══════════════════════════════════════════════

    public function test_payment_admin_has_full_access(): void
    {
        $policy = new PaymentPolicy;
        $model = $this->makeForTenant(Payment::class, $this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $model));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->delete($this->admin, $model));
    }

    public function test_payment_no_permission_denies_all(): void
    {
        $policy = new PaymentPolicy;
        $model = $this->makeForTenant(Payment::class, $this->tenant->id);

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $model));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->delete($this->noPerms, $model));
    }

    public function test_payment_cross_tenant_denies(): void
    {
        $policy = new PaymentPolicy;
        $foreign = $this->makeForTenant(Payment::class, $this->otherTenant->id);

        $this->assertFalse($policy->view($this->admin, $foreign));
        $this->assertFalse($policy->delete($this->admin, $foreign));
    }

    // ═══════════════════════════════════════════════
    // FiscalNotePolicy (viewAny, view, create, update, delete, cancel)
    // ═══════════════════════════════════════════════

    public function test_fiscal_note_admin_has_full_access(): void
    {
        $policy = new FiscalNotePolicy;
        $model = $this->makeForTenant(FiscalNote::class, $this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $model));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $model));
        $this->assertTrue($policy->delete($this->admin, $model));
        $this->assertTrue($policy->cancel($this->admin, $model));
    }

    public function test_fiscal_note_no_permission_denies_all(): void
    {
        $policy = new FiscalNotePolicy;
        $model = $this->makeForTenant(FiscalNote::class, $this->tenant->id);

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $model));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->update($this->noPerms, $model));
        $this->assertFalse($policy->delete($this->noPerms, $model));
        $this->assertFalse($policy->cancel($this->noPerms, $model));
    }

    public function test_fiscal_note_cross_tenant_denies(): void
    {
        $policy = new FiscalNotePolicy;
        $foreign = $this->makeForTenant(FiscalNote::class, $this->otherTenant->id);

        $this->assertFalse($policy->view($this->admin, $foreign));
        $this->assertFalse($policy->update($this->admin, $foreign));
        $this->assertFalse($policy->delete($this->admin, $foreign));
        $this->assertFalse($policy->cancel($this->admin, $foreign));
    }
}
