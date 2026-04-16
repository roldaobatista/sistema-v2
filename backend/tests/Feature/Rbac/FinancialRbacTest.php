<?php

use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountPayableCategory;
use App\Models\AccountReceivable;
use App\Models\BankAccount;
use App\Models\CommissionEvent;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureTenantScope::class,
    ]);

    $this->tenant = Tenant::factory()->create();
    $this->category = AccountPayableCategory::factory()->create(['tenant_id' => $this->tenant->id]);
    app()->instance('current_tenant_id', $this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
});

function financialUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'current_tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    setPermissionsTeamId($tenant->id);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $user->givePermissionTo($perm);
    }

    return $user;
}

// ============================================================
// Accounts Payable - CRUD
// ============================================================

test('user WITH finance.payable.view can list accounts payable', function () {
    $user = financialUser($this->tenant, ['finance.payable.view']);
    Sanctum::actingAs($user, ['*']);

    AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
    ]);

    $this->getJson('/api/v1/accounts-payable')->assertOk();
});

test('user WITHOUT finance.payable.view gets 403 on list accounts payable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertForbidden();
});

test('user WITH finance.payable.view can show an account payable', function () {
    $user = financialUser($this->tenant, ['finance.payable.view']);
    Sanctum::actingAs($user, ['*']);

    $ap = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/accounts-payable/{$ap->id}")->assertOk();
});

test('user WITHOUT finance.payable.view gets 403 on show account payable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $ap = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/accounts-payable/{$ap->id}")->assertForbidden();
});

test('user WITH finance.payable.view can access accounts payable summary', function () {
    $user = financialUser($this->tenant, ['finance.payable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable-summary')->assertOk();
});

test('user WITHOUT finance.payable.view gets 403 on accounts payable summary', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable-summary')->assertForbidden();
});

test('user WITH finance.payable.create can store account payable', function () {
    $user = financialUser($this->tenant, ['finance.payable.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/accounts-payable', [
        'category_id' => $this->category->id,
        'description' => 'Conta de luz',
        'amount' => 350.00,
        'due_date' => now()->addDays(30)->toDateString(),
        'payment_method' => 'boleto',
    ])->assertStatus(201);
});

test('user WITHOUT finance.payable.create gets 403 on store account payable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/accounts-payable', [
        'description' => 'Conta de luz',
        'amount' => 350.00,
        'due_date' => now()->addDays(30)->toDateString(),
    ])->assertForbidden();
});

test('user WITH finance.payable.update can update account payable', function () {
    $user = financialUser($this->tenant, ['finance.payable.update']);
    Sanctum::actingAs($user, ['*']);

    $ap = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
    ]);

    $this->putJson("/api/v1/accounts-payable/{$ap->id}", [
        'description' => 'Conta atualizada',
    ])->assertOk();
});

test('user WITHOUT finance.payable.update gets 403 on update account payable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $ap = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
    ]);

    $this->putJson("/api/v1/accounts-payable/{$ap->id}", [
        'description' => 'Conta atualizada',
    ])->assertForbidden();
});

test('user WITH finance.payable.delete can delete account payable', function () {
    $user = financialUser($this->tenant, ['finance.payable.delete']);
    Sanctum::actingAs($user, ['*']);

    $ap = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
    ]);

    $this->deleteJson("/api/v1/accounts-payable/{$ap->id}")->assertNoContent();
});

test('user WITHOUT finance.payable.delete gets 403 on delete account payable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $ap = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
    ]);

    $this->deleteJson("/api/v1/accounts-payable/{$ap->id}")->assertForbidden();
});

test('user WITH finance.payable.settle can pay account payable', function () {
    $user = financialUser($this->tenant, ['finance.payable.settle']);
    Sanctum::actingAs($user, ['*']);

    $ap = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/accounts-payable/{$ap->id}/pay", [
        'amount' => $ap->amount,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'pix',
    ])->assertSuccessful();
});

test('user WITHOUT finance.payable.settle gets 403 on pay account payable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $ap = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/accounts-payable/{$ap->id}/pay", [
        'amount' => $ap->amount,
    ])->assertForbidden();
});

// ============================================================
// Accounts Receivable - CRUD
// ============================================================

test('user WITH finance.receivable.view can list accounts receivable', function () {
    $user = financialUser($this->tenant, ['finance.receivable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-receivable')->assertOk();
});

test('user WITHOUT finance.receivable.view gets 403 on list accounts receivable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-receivable')->assertForbidden();
});

test('user WITH finance.receivable.view can show account receivable', function () {
    $user = financialUser($this->tenant, ['finance.receivable.view']);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/accounts-receivable/{$ar->id}")->assertOk();
});

test('user WITHOUT finance.receivable.view gets 403 on show account receivable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/accounts-receivable/{$ar->id}")->assertForbidden();
});

test('user WITH finance.receivable.view can access receivable summary', function () {
    $user = financialUser($this->tenant, ['finance.receivable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-receivable-summary')->assertOk();
});

test('user WITHOUT finance.receivable.view gets 403 on receivable summary', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-receivable-summary')->assertForbidden();
});

test('user WITH finance.receivable.create can store account receivable', function () {
    $user = financialUser($this->tenant, ['finance.receivable.create']);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->postJson('/api/v1/accounts-receivable', [
        'customer_id' => $customer->id,
        'description' => 'Servico prestado',
        'amount' => 1500.00,
        'due_date' => now()->addDays(30)->toDateString(),
        'payment_method' => 'boleto',
    ])->assertStatus(201);
});

test('user WITHOUT finance.receivable.create gets 403 on store account receivable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/accounts-receivable', [
        'description' => 'Servico prestado',
        'amount' => 1500.00,
    ])->assertForbidden();
});

test('user WITH finance.receivable.update can update account receivable', function () {
    $user = financialUser($this->tenant, ['finance.receivable.update']);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    $this->putJson("/api/v1/accounts-receivable/{$ar->id}", [
        'description' => 'Servico atualizado',
    ])->assertOk();
});

test('user WITHOUT finance.receivable.update gets 403 on update account receivable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    $this->putJson("/api/v1/accounts-receivable/{$ar->id}", [
        'description' => 'Servico atualizado',
    ])->assertForbidden();
});

test('user WITH finance.receivable.delete can delete account receivable', function () {
    $user = financialUser($this->tenant, ['finance.receivable.delete']);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    $this->deleteJson("/api/v1/accounts-receivable/{$ar->id}")->assertNoContent();
});

test('user WITHOUT finance.receivable.delete gets 403 on delete account receivable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    $this->deleteJson("/api/v1/accounts-receivable/{$ar->id}")->assertForbidden();
});

test('user WITH finance.receivable.settle can pay account receivable', function () {
    $user = financialUser($this->tenant, ['finance.receivable.settle']);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/accounts-receivable/{$ar->id}/pay", [
        'amount' => $ar->amount,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'pix',
    ])->assertSuccessful();
});

test('user WITHOUT finance.receivable.settle gets 403 on pay account receivable', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/accounts-receivable/{$ar->id}/pay", [
        'amount' => $ar->amount,
    ])->assertForbidden();
});

// ============================================================
// Commissions
// ============================================================

test('user WITH commissions.rule.view can list commission rules', function () {
    $user = financialUser($this->tenant, ['commissions.rule.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-rules')->assertOk();
});

test('user WITHOUT commissions.rule.view gets 403 on list commission rules', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-rules')->assertForbidden();
});

test('user WITH commissions.event.view can list commission events', function () {
    $user = financialUser($this->tenant, ['commissions.event.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-events')->assertOk();
});

test('user WITHOUT commissions.event.view gets 403 on commission events', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-events')->assertForbidden();
});

test('user WITH commissions.settlement.view can list commission settlements', function () {
    $user = financialUser($this->tenant, ['commissions.settlement.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-settlements')->assertOk();
});

test('user WITHOUT commissions.settlement.view gets 403 on commission settlements', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-settlements')->assertForbidden();
});

test('user WITH commissions.event.view can access commission summary', function () {
    $user = financialUser($this->tenant, ['commissions.event.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-summary')->assertOk();
});

test('user WITHOUT commissions.event.view gets 403 on commission summary', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-summary')->assertForbidden();
});

test('user WITH commissions.rule.create can store commission rule', function () {
    $user = financialUser($this->tenant, ['commissions.rule.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/commission-rules', [
        'name' => 'Regra Tecnico',
        'type' => 'percentage',
        'value' => 10,
        'applies_to' => 'all',
        'calculation_type' => 'percent_gross',
    ])->assertStatus(201);
});

test('user WITHOUT commissions.rule.create gets 403 on store commission rule', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/commission-rules', [
        'name' => 'Regra Tecnico',
    ])->assertForbidden();
});

// ============================================================
// Financial Reports
// ============================================================

test('user WITH reports.financial_report.view can access financial reports', function () {
    $user = financialUser($this->tenant, ['reports.financial_report.view', 'reports.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/reports/financial')->assertOk();
});

test('user WITHOUT reports.financial_report.view gets 403 on financial reports', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/reports/financial')->assertForbidden();
});

test('user WITH reports.commission_report.view can access commissions report', function () {
    $user = financialUser($this->tenant, ['reports.commission_report.view', 'reports.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/reports/commissions')->assertOk();
});

test('user WITHOUT reports.commission_report.view gets 403 on commissions report', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/reports/commissions')->assertForbidden();
});

// ============================================================
// Cash Flow / Financial Summary
// ============================================================

test('user WITH finance.cashflow.view can access financial summary', function () {
    $user = financialUser($this->tenant, ['finance.cashflow.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/financial/summary')->assertOk();
});

test('user WITHOUT finance.cashflow.view gets 403 on financial summary', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/financial/summary')->assertForbidden();
});

// ============================================================
// Bank Reconciliation
// ============================================================

test('user WITH finance.receivable.view can access bank reconciliation summary', function () {
    $user = financialUser($this->tenant, ['finance.receivable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/bank-reconciliation/summary')->assertOk();
});

test('user WITHOUT finance.receivable.view or finance.payable.view gets 403 on bank reconciliation', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/bank-reconciliation/summary')->assertForbidden();
});

test('user WITH finance.payable.view can access bank reconciliation summary', function () {
    $user = financialUser($this->tenant, ['finance.payable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/bank-reconciliation/summary')->assertOk();
});

test('user WITH finance.receivable.view can list bank reconciliation statements', function () {
    $user = financialUser($this->tenant, ['finance.receivable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/bank-reconciliation/statements')->assertOk();
});

test('user WITHOUT finance.receivable.view or finance.payable.view gets 403 on bank statements', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/bank-reconciliation/statements')->assertForbidden();
});

test('user WITH finance.receivable.view can access bank reconciliation dashboard', function () {
    $user = financialUser($this->tenant, ['finance.receivable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/bank-reconciliation/dashboard')->assertOk();
});

test('user WITHOUT proper permissions gets 403 on bank reconciliation dashboard', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/bank-reconciliation/dashboard')->assertForbidden();
});

// ============================================================
// Fund Transfers
// ============================================================

test('user WITH financial.fund_transfer.view can list fund transfers', function () {
    $user = financialUser($this->tenant, ['financial.fund_transfer.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/fund-transfers')->assertOk();
});

test('user WITHOUT financial.fund_transfer.view gets 403 on fund transfers', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/fund-transfers')->assertForbidden();
});

test('user WITH financial.fund_transfer.create can store fund transfer', function () {
    $user = financialUser($this->tenant, ['financial.fund_transfer.create']);
    Sanctum::actingAs($user, ['*']);

    $bankAccount = BankAccount::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $user->id,
    ]);
    $toUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->postJson('/api/v1/fund-transfers', [
        'bank_account_id' => $bankAccount->id,
        'to_user_id' => $toUser->id,
        'amount' => 500.00,
        'transfer_date' => now()->toDateString(),
        'payment_method' => 'pix',
        'description' => 'Adiantamento tecnico',
    ])->assertStatus(201);
});

test('user WITHOUT financial.fund_transfer.create gets 403 on store fund transfer', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/fund-transfers', [
        'amount' => 500.00,
    ])->assertForbidden();
});

test('user WITH financial.fund_transfer.view can access fund transfer summary', function () {
    $user = financialUser($this->tenant, ['financial.fund_transfer.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/fund-transfers/summary')->assertOk();
});

test('user WITHOUT financial.fund_transfer.view gets 403 on fund transfer summary', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/fund-transfers/summary')->assertForbidden();
});

// ============================================================
// Bank Accounts
// ============================================================

test('user WITH financial.bank_account.view can list bank accounts', function () {
    $user = financialUser($this->tenant, ['financial.bank_account.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/bank-accounts')->assertOk();
});

test('user WITHOUT financial.bank_account.view gets 403 on bank accounts', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/bank-accounts')->assertForbidden();
});

test('user WITH financial.bank_account.create can store bank account', function () {
    $user = financialUser($this->tenant, ['financial.bank_account.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/bank-accounts', [
        'name' => 'Conta Corrente Bradesco',
        'bank_name' => 'Bradesco',
        'agency' => '1234',
        'account_number' => '56789-0',
        'account_type' => 'corrente',
        'balance' => 0,
    ])->assertStatus(201);
});

test('user WITHOUT financial.bank_account.create gets 403 on store bank account', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/bank-accounts', [
        'name' => 'Conta',
    ])->assertForbidden();
});

// ============================================================
// Chart of Accounts
// ============================================================

test('user WITH finance.chart.view can list chart of accounts', function () {
    $user = financialUser($this->tenant, ['finance.chart.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/chart-of-accounts')->assertOk();
});

test('user WITHOUT finance.chart.view gets 403 on chart of accounts', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/chart-of-accounts')->assertForbidden();
});

test('user WITH finance.chart.create can store chart of account', function () {
    $user = financialUser($this->tenant, ['finance.chart.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/chart-of-accounts', [
        'code' => '1.1.01',
        'name' => 'Caixa',
        'type' => 'asset',
    ])->assertStatus(201);
});

test('user WITHOUT finance.chart.create gets 403 on store chart of account', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/chart-of-accounts', [
        'code' => '1.1.01',
        'name' => 'Caixa',
    ])->assertForbidden();
});

// ============================================================
// Expenses
// ============================================================

test('user WITH expenses.expense.view can list expenses', function () {
    $user = financialUser($this->tenant, ['expenses.expense.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/expenses')->assertOk();
});

test('user WITHOUT expenses.expense.view gets 403 on list expenses', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/expenses')->assertForbidden();
});

test('user WITH expenses.expense.create can store expense', function () {
    $user = financialUser($this->tenant, ['expenses.expense.create', 'technicians.cashbox.expense.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/expenses', [
        'description' => 'Aluguel escritorio',
        'amount' => 2000.00,
        'expense_date' => now()->toDateString(),
    ])->assertStatus(201);
});

test('user WITHOUT expenses.expense.create gets 403 on store expense', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/expenses', [
        'description' => 'Aluguel',
    ])->assertForbidden();
});

// ============================================================
// Payments
// ============================================================

test('user WITH finance.receivable.view can list payments', function () {
    $user = financialUser($this->tenant, ['finance.receivable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/payments')->assertOk();
});

test('user WITHOUT finance.receivable.view or finance.payable.view gets 403 on payments', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/payments')->assertForbidden();
});

// ============================================================
// Account Payable Categories
// ============================================================

test('user WITH finance.payable.view can list account payable categories', function () {
    $user = financialUser($this->tenant, ['finance.payable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/account-payable-categories')->assertOk();
});

test('user WITHOUT finance.payable.view gets 403 on account payable categories', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/account-payable-categories')->assertForbidden();
});

// ============================================================
// Financial Export
// ============================================================

test('user WITH finance.receivable.view can export financial OFX', function () {
    $user = financialUser($this->tenant, ['finance.receivable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/financial/export/ofx?type=receivable&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())->assertOk();
});

test('user WITHOUT finance.receivable.view or finance.payable.view gets 403 on export', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/financial/export/ofx')->assertForbidden();
});

// ============================================================
// Commission Disputes
// ============================================================

test('user WITH commissions.dispute.view can list commission disputes', function () {
    $user = financialUser($this->tenant, ['commissions.dispute.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-disputes')->assertOk();
});

test('user WITHOUT commissions.dispute.view gets 403 on commission disputes', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-disputes')->assertForbidden();
});

test('user WITH commissions.dispute.create can store commission dispute', function () {
    $user = financialUser($this->tenant, ['commissions.dispute.create']);
    Sanctum::actingAs($user, ['*']);

    // Create a commission event to reference (user_id must match current user or have resolve permission)
    $commissionEvent = CommissionEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $user->id,
    ]);

    $this->postJson('/api/v1/commission-disputes', [
        'commission_event_id' => $commissionEvent->id,
        'reason' => 'Valor incorreto na comissao calculada',
    ])->assertStatus(201);
});

test('user WITHOUT commissions.dispute.create gets 403 on store commission dispute', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/commission-disputes', [
        'description' => 'Valor incorreto',
    ])->assertForbidden();
});

// ============================================================
// Commission Goals
// ============================================================

test('user WITH commissions.goal.view can list commission goals', function () {
    $user = financialUser($this->tenant, ['commissions.goal.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-goals')->assertOk();
});

test('user WITHOUT commissions.goal.view gets 403 on commission goals', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-goals')->assertForbidden();
});

// ============================================================
// Commission Campaigns
// ============================================================

test('user WITH commissions.campaign.view can list commission campaigns', function () {
    $user = financialUser($this->tenant, ['commissions.campaign.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-campaigns')->assertOk();
});

test('user WITHOUT commissions.campaign.view gets 403 on commission campaigns', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/commission-campaigns')->assertForbidden();
});

// ============================================================
// Recurring Commissions
// ============================================================

test('user WITH commissions.recurring.view can list recurring commissions', function () {
    $user = financialUser($this->tenant, ['commissions.recurring.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/recurring-commissions')->assertOk();
});

test('user WITHOUT commissions.recurring.view gets 403 on recurring commissions', function () {
    $user = financialUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/recurring-commissions')->assertForbidden();
});
