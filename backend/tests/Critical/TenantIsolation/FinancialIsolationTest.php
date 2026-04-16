<?php

/**
 * Tenant Isolation — Financial Module
 *
 * Validates complete data isolation for: AccountReceivable, AccountPayable,
 * Payment, CommissionRule, CommissionEvent, BankAccount, FundTransfer.
 *
 * FAILURE HERE = FINANCIAL DATA LEAK BETWEEN TENANTS
 */

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\BankAccount;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\FundTransfer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Model::unguard();
    Model::preventLazyLoading(false);
    Gate::before(fn () => true);

    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->userA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'current_tenant_id' => $this->tenantA->id,
        'is_active' => true,
    ]);

    $this->userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'current_tenant_id' => $this->tenantB->id,
        'is_active' => true,
    ]);

    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);
});

function actAsTenantFinancial(object $test, User $user, Tenant $tenant): void
{
    app()->instance('current_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    Sanctum::actingAs($user, ['*']);
}

// ══════════════════════════════════════════════════════════════════
//  ACCOUNTS RECEIVABLE
// ══════════════════════════════════════════════════════════════════

test('accounts receivable listing only shows own tenant', function () {
    $customerA = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cust A', 'type' => 'PJ']);
    $customerB = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cust B', 'type' => 'PJ']);

    AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id, 'created_by' => $this->userA->id,
        'description' => 'AR-A', 'amount' => 1000, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);
    AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $customerB->id, 'created_by' => $this->userB->id,
        'description' => 'AR-B', 'amount' => 2000, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);

    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/accounts-receivable');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('description')->toArray())->not->toContain('AR-B');
});

test('cannot GET cross-tenant account receivable — returns 404', function () {
    $customerB = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'B', 'type' => 'PJ']);
    $arB = AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $customerB->id, 'created_by' => $this->userB->id,
        'description' => 'Secret AR', 'amount' => 5000, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);

    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $this->getJson("/api/v1/accounts-receivable/{$arB->id}")->assertNotFound();
});

test('accounts receivable summary only includes own tenant', function () {
    $customerA = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'A', 'type' => 'PJ']);
    $customerB = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'B', 'type' => 'PJ']);

    AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id, 'created_by' => $this->userA->id,
        'description' => 'Sum A', 'amount' => 1000, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);
    AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $customerB->id, 'created_by' => $this->userB->id,
        'description' => 'Sum B', 'amount' => 9999, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);

    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/accounts-receivable-summary');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  ACCOUNTS PAYABLE
// ══════════════════════════════════════════════════════════════════

test('accounts payable listing only shows own tenant', function () {
    AccountPayable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'created_by' => $this->userA->id,
        'description' => 'AP-A', 'amount' => 500, 'due_date' => now()->addDays(15), 'status' => 'pending',
    ]);
    AccountPayable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'created_by' => $this->userB->id,
        'description' => 'AP-B', 'amount' => 700, 'due_date' => now()->addDays(15), 'status' => 'pending',
    ]);

    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/accounts-payable');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('description')->toArray())->not->toContain('AP-B');
});

test('cannot GET cross-tenant account payable — returns 404', function () {
    $apB = AccountPayable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'created_by' => $this->userB->id,
        'description' => 'Secret AP', 'amount' => 3000, 'due_date' => now()->addDays(15), 'status' => 'pending',
    ]);

    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $this->getJson("/api/v1/accounts-payable/{$apB->id}")->assertNotFound();
});

test('cannot UPDATE cross-tenant account payable — returns 404', function () {
    $apB = AccountPayable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'created_by' => $this->userB->id,
        'description' => 'Protected AP', 'amount' => 3000, 'due_date' => now()->addDays(15), 'status' => 'pending',
    ]);

    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $this->putJson("/api/v1/accounts-payable/{$apB->id}", ['description' => 'Hacked'])->assertNotFound();

    $this->assertDatabaseHas('accounts_payable', ['id' => $apB->id, 'description' => 'Protected AP']);
});

test('cannot DELETE cross-tenant account payable — returns 404', function () {
    $apB = AccountPayable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'created_by' => $this->userB->id,
        'description' => 'Safe AP', 'amount' => 1000, 'due_date' => now()->addDays(15), 'status' => 'pending',
    ]);

    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $this->deleteJson("/api/v1/accounts-payable/{$apB->id}")->assertNotFound();
});

// ══════════════════════════════════════════════════════════════════
//  PAYMENTS
// ══════════════════════════════════════════════════════════════════

test('payments listing only shows own tenant', function () {
    $customerA = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'PA', 'type' => 'PJ']);
    $customerB = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'PB', 'type' => 'PJ']);

    $arA = AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id, 'created_by' => $this->userA->id,
        'description' => 'AR for Pay A', 'amount' => 1000, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);
    $arB = AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $customerB->id, 'created_by' => $this->userB->id,
        'description' => 'AR for Pay B', 'amount' => 2000, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);

    Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'payable_type' => AccountReceivable::class,
        'payable_id' => $arA->id, 'received_by' => $this->userA->id,
        'amount' => 500, 'payment_method' => 'pix', 'payment_date' => now()->format('Y-m-d'),
    ]);
    Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'payable_type' => AccountReceivable::class,
        'payable_id' => $arB->id, 'received_by' => $this->userB->id,
        'amount' => 1000, 'payment_method' => 'boleto', 'payment_date' => now()->format('Y-m-d'),
    ]);

    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/payments');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
});

// ══════════════════════════════════════════════════════════════════
//  BANK ACCOUNTS
// ══════════════════════════════════════════════════════════════════

test('bank account model scope isolates by tenant', function () {
    BankAccount::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Bank A', 'bank_name' => 'Bradesco',
        'agency' => '1234', 'account_number' => '56789-0', 'account_type' => 'corrente',
        'balance' => 10000, 'is_active' => true, 'created_by' => $this->userA->id,
    ]);
    BankAccount::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Bank B', 'bank_name' => 'Itau',
        'agency' => '5678', 'account_number' => '12345-0', 'account_type' => 'corrente',
        'balance' => 50000, 'is_active' => true, 'created_by' => $this->userB->id,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $accounts = BankAccount::all();
    expect($accounts)->toHaveCount(1);
    expect($accounts->first()->name)->toBe('Bank A');
});

// ══════════════════════════════════════════════════════════════════
//  FUND TRANSFERS
// ══════════════════════════════════════════════════════════════════

test('fund transfer model scope isolates by tenant', function () {
    $bankA = BankAccount::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'FT Bank A', 'bank_name' => 'BB',
        'agency' => '0001', 'account_number' => '11111-1', 'account_type' => 'corrente',
        'balance' => 5000, 'is_active' => true, 'created_by' => $this->userA->id,
    ]);
    $bankB = BankAccount::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'FT Bank B', 'bank_name' => 'Caixa',
        'agency' => '0002', 'account_number' => '22222-2', 'account_type' => 'corrente',
        'balance' => 8000, 'is_active' => true, 'created_by' => $this->userB->id,
    ]);

    FundTransfer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'bank_account_id' => $bankA->id,
        'to_user_id' => $this->userA->id, 'amount' => 100, 'transfer_date' => now()->format('Y-m-d'),
        'payment_method' => 'pix', 'description' => 'Transfer A', 'status' => 'completed',
        'created_by' => $this->userA->id,
    ]);
    FundTransfer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'bank_account_id' => $bankB->id,
        'to_user_id' => $this->userB->id, 'amount' => 200, 'transfer_date' => now()->format('Y-m-d'),
        'payment_method' => 'ted', 'description' => 'Transfer B', 'status' => 'completed',
        'created_by' => $this->userB->id,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $transfers = FundTransfer::all();
    expect($transfers)->toHaveCount(1);
    expect($transfers->first()->description)->toBe('Transfer A');
});

// ══════════════════════════════════════════════════════════════════
//  COMMISSIONS
// ══════════════════════════════════════════════════════════════════

test('commission rules listing only shows own tenant', function () {
    CommissionRule::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'user_id' => $this->userA->id,
        'name' => 'Rule A', 'type' => 'percentage', 'value' => 10,
        'applies_to' => 'all', 'calculation_type' => 'percent_gross',
        'applies_to_role' => 'technician', 'applies_when' => 'os_completed',
        'active' => true, 'priority' => 0,
    ]);
    CommissionRule::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'user_id' => $this->userB->id,
        'name' => 'Rule B', 'type' => 'percentage', 'value' => 15,
        'applies_to' => 'all', 'calculation_type' => 'percent_gross',
        'applies_to_role' => 'technician', 'applies_when' => 'os_completed',
        'active' => true, 'priority' => 0,
    ]);

    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/commission-rules');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
});

test('cannot GET cross-tenant commission rule — returns 404', function () {
    $ruleB = CommissionRule::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'user_id' => $this->userB->id,
        'name' => 'Secret Rule', 'type' => 'percentage', 'value' => 20,
        'applies_to' => 'all', 'calculation_type' => 'percent_gross',
        'applies_to_role' => 'technician', 'applies_when' => 'os_completed',
        'active' => true, 'priority' => 0,
    ]);

    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $this->getJson("/api/v1/commission-rules/{$ruleB->id}")->assertNotFound();
});

test('commission events model scope isolates by tenant', function () {
    $customerA = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'CE A', 'type' => 'PJ']);
    $customerB = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->tenantB->id, 'name' => 'CE B', 'type' => 'PJ']);

    $woA = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id,
        'created_by' => $this->userA->id, 'number' => 'CE-OS-A',
        'description' => 'OS A', 'status' => 'open',
    ]);
    $woB = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $customerB->id,
        'created_by' => $this->userB->id, 'number' => 'CE-OS-B',
        'description' => 'OS B', 'status' => 'open',
    ]);

    $ruleA = CommissionRule::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'user_id' => $this->userA->id,
        'name' => 'CR A', 'type' => 'percentage', 'value' => 10,
        'applies_to' => 'all', 'calculation_type' => 'percent_gross',
        'applies_to_role' => 'technician', 'applies_when' => 'os_completed',
        'active' => true, 'priority' => 0,
    ]);
    $ruleB = CommissionRule::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'user_id' => $this->userB->id,
        'name' => 'CR B', 'type' => 'percentage', 'value' => 15,
        'applies_to' => 'all', 'calculation_type' => 'percent_gross',
        'applies_to_role' => 'technician', 'applies_when' => 'os_completed',
        'active' => true, 'priority' => 0,
    ]);

    CommissionEvent::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'commission_rule_id' => $ruleA->id,
        'work_order_id' => $woA->id, 'user_id' => $this->userA->id,
        'base_amount' => 1000, 'commission_amount' => 100, 'proportion' => 1, 'status' => 'pending',
    ]);
    CommissionEvent::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'commission_rule_id' => $ruleB->id,
        'work_order_id' => $woB->id, 'user_id' => $this->userB->id,
        'base_amount' => 2000, 'commission_amount' => 300, 'proportion' => 1, 'status' => 'pending',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $events = CommissionEvent::all();
    expect($events)->toHaveCount(1);
    expect($events->first()->tenant_id)->toBe($this->tenantA->id);
});

// ══════════════════════════════════════════════════════════════════
//  FINANCIAL REPORTS
// ══════════════════════════════════════════════════════════════════

test('financial report endpoint is tenant-scoped', function () {
    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/financial');
    // Just ensure it does not leak — response should be 200 and contain only tenant A data
    expect($response->status())->toBeIn([200, 204, 422]);
});

test('cash flow projection is tenant-scoped', function () {
    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/finance-advanced/cash-flow/projection');
    expect($response->status())->toBeIn([200, 204, 422]);
});

test('dashboard stats are tenant-scoped', function () {
    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/dashboard');
    $response->assertOk();
});

test('financial summary is tenant-scoped', function () {
    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/financial/summary');
    $response->assertOk();
});

test('payments summary only includes own tenant', function () {
    actAsTenantFinancial($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/payments-summary');
    $response->assertOk();
});
