<?php

namespace Tests\Unit\Models;

use App\Enums\FinancialStatus;
use App\Models\AccountPayable;
use App\Models\AccountPayableCategory;
use App\Models\AccountReceivable;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementEntry;
use App\Models\FundTransfer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FinancialDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── AccountPayable ──

    public function test_payable_status_pending(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
        ]);
        $this->assertEquals(FinancialStatus::PENDING, $ap->status);
    }

    public function test_payable_status_paid(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PAID,
            'paid_at' => now(),
        ]);
        $this->assertEquals(FinancialStatus::PAID, $ap->status);
    }

    public function test_payable_overdue_detection(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'due_date' => now()->subDays(5),
            'status' => FinancialStatus::PENDING,
        ]);
        $this->assertTrue($ap->due_date->isPast());
    }

    public function test_payable_has_category(): void
    {
        $cat = AccountPayableCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'category_id' => $cat->id,
        ]);
        $this->assertInstanceOf(AccountPayableCategory::class, $ap->categoryRelation);
    }

    public function test_payable_has_payments(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
        ]);
        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $ap->id,
            'amount' => '500.00',
        ]);
        $this->assertGreaterThanOrEqual(1, $ap->payments()->count());
    }

    public function test_payable_soft_deletes(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        $ap->delete();
        $this->assertSoftDeleted('accounts_payable', ['id' => $ap->id]);
    }

    public function test_payable_amount_decimal_cast(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '9999.99',
        ]);
        $this->assertEquals('9999.99', $ap->amount);
    }

    // ── AccountReceivable ──

    public function test_receivable_creation(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertNotNull($ar);
    }

    public function test_receivable_has_payments(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '2000.00',
            'amount_paid' => '0.00',
        ]);
        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $ar->id,
            'amount' => '1000.00',
        ]);
        $this->assertGreaterThanOrEqual(1, $ar->payments()->count());
    }

    public function test_receivable_status_transitions(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => FinancialStatus::PENDING,
        ]);
        $ar->update(['status' => FinancialStatus::PAID, 'paid_at' => now()]);
        $this->assertEquals(FinancialStatus::PAID, $ar->fresh()->status);
    }

    // ── BankAccount ──

    public function test_bank_account_creation(): void
    {
        $ba = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($ba);
    }

    public function test_bank_account_balance(): void
    {
        $ba = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => '50000.00',
        ]);
        $this->assertEquals('50000.00', $ba->balance);
    }

    public function test_bank_account_soft_deletes(): void
    {
        $ba = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $ba->delete();
        $this->assertNotNull(BankAccount::withTrashed()->find($ba->id));
    }

    public function test_bank_account_has_statements(): void
    {
        $ba = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        BankStatement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $ba->id,
        ]);
        $this->assertGreaterThanOrEqual(1, $ba->statements()->count());
    }

    // ── BankStatement ──

    public function test_statement_has_entries(): void
    {
        $ba = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $bs = BankStatement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $ba->id,
        ]);
        BankStatementEntry::factory()->count(5)->create([
            'bank_statement_id' => $bs->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertGreaterThanOrEqual(5, $bs->entries()->count());
    }

    public function test_statement_entry_credit(): void
    {
        $ba = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $bs = BankStatement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $ba->id,
        ]);
        $entry = BankStatementEntry::factory()->create([
            'bank_statement_id' => $bs->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'credit',
            'amount' => '1000.00',
        ]);
        $this->assertEquals('credit', $entry->type);
    }

    public function test_statement_entry_debit(): void
    {
        $ba = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $bs = BankStatement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $ba->id,
        ]);
        $entry = BankStatementEntry::factory()->create([
            'bank_statement_id' => $bs->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'debit',
            'amount' => '500.00',
        ]);
        $this->assertEquals('debit', $entry->type);
    }

    // ── FundTransfer ──

    public function test_fund_transfer_creation(): void
    {
        $ba = BankAccount::factory()->create(['tenant_id' => $this->tenant->id, 'balance' => 10000]);
        $transfer = FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $ba->id,
            'to_user_id' => $this->user->id,
            'amount' => '3000.00',
        ]);
        $this->assertNotNull($transfer);
        $this->assertEquals('3000.00', $transfer->amount);
    }

    public function test_fund_transfer_belongs_to_bank_account(): void
    {
        $ba = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $transfer = FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $ba->id,
            'to_user_id' => $this->user->id,
        ]);
        $this->assertInstanceOf(BankAccount::class, $transfer->bankAccount);
        $this->assertInstanceOf(User::class, $transfer->technician);
    }

    // ── Scopes ──

    public function test_payable_scope_pending(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
        ]);
        $results = AccountPayable::where('status', FinancialStatus::PENDING)->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_payable_scope_overdue(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->subDays(10),
        ]);
        $results = AccountPayable::where('status', FinancialStatus::PENDING)
            ->where('due_date', '<', now())->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_payable_scope_this_month(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'due_date' => now(),
        ]);
        $results = AccountPayable::whereMonth('due_date', now()->month)->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    // ── Aggregations ──

    public function test_total_payables_sum(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'status' => FinancialStatus::PENDING,
        ]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '2000.00',
            'status' => FinancialStatus::PENDING,
        ]);
        $total = AccountPayable::where('status', FinancialStatus::PENDING)->sum('amount');
        $this->assertGreaterThanOrEqual(3000, $total);
    }

    // ── Recalculate Status ──

    public function test_payable_recalculate_status_to_paid(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'amount_paid' => '1000.00',
            'status' => FinancialStatus::PENDING,
        ]);
        $ap->recalculateStatus();
        $this->assertEquals(FinancialStatus::PAID, $ap->fresh()->status);
    }

    public function test_payable_recalculate_status_to_partial(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'amount_paid' => '500.00',
            'status' => FinancialStatus::PENDING,
        ]);
        $ap->recalculateStatus();
        $this->assertEquals(FinancialStatus::PARTIAL, $ap->fresh()->status);
    }

    public function test_receivable_recalculate_status_preserves_cancelled(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1000.00',
            'amount_paid' => '1000.00',
            'status' => FinancialStatus::CANCELLED,
        ]);
        $ar->recalculateStatus();
        $this->assertEquals(FinancialStatus::CANCELLED, $ar->fresh()->status);
    }
}
