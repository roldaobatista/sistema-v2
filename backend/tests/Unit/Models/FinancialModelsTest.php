<?php

namespace Tests\Unit\Models;

use App\Enums\FinancialStatus;
use App\Models\AccountPayable;
use App\Models\AccountPayableCategory;
use App\Models\AccountReceivable;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementEntry;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\FundTransfer;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FinancialModelsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── AccountPayable — Relationships ──

    public function test_payable_belongs_to_creator(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $ap->creator);
    }

    public function test_payable_belongs_to_supplier(): void
    {
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'supplier_id' => $supplier->id,
        ]);

        $this->assertInstanceOf(Supplier::class, $ap->supplierRelation);
    }

    public function test_payable_belongs_to_category(): void
    {
        $cat = AccountPayableCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'category_id' => $cat->id,
        ]);

        $this->assertInstanceOf(AccountPayableCategory::class, $ap->categoryRelation);
    }

    public function test_payable_belongs_to_chart_of_account(): void
    {
        $coa = ChartOfAccount::create(['tenant_id' => $this->tenant->id, 'code' => '1.1', 'name' => 'Test Account', 'type' => 'expense']);
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'chart_of_account_id' => $coa->id,
        ]);

        $this->assertInstanceOf(ChartOfAccount::class, $ap->chartOfAccount);
    }

    public function test_payable_has_morph_many_payments(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(MorphMany::class, $ap->payments());
    }

    // ── AccountPayable — recalculateStatus ──

    public function test_recalculate_status_sets_paid_when_fully_paid(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'amount_paid' => '1000.00',
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->addDays(30),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::PAID, $ap->status);
    }

    public function test_recalculate_status_sets_overdue_when_past_due(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->subDays(5),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::OVERDUE, $ap->status);
    }

    public function test_recalculate_status_sets_partial_when_partially_paid(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'amount_paid' => '500.00',
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->addDays(30),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::PARTIAL, $ap->status);
    }

    public function test_recalculate_status_keeps_pending_when_not_paid(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->addDays(30),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::PENDING, $ap->status);
    }

    public function test_recalculate_status_does_not_change_cancelled(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'amount_paid' => '1000.00',
            'status' => FinancialStatus::CANCELLED,
            'due_date' => now()->addDays(30),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::CANCELLED, $ap->status);
    }

    public function test_recalculate_status_does_not_change_renegotiated(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::RENEGOTIATED,
            'due_date' => now()->subDays(10),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::RENEGOTIATED, $ap->status);
    }

    // ── AccountPayable — Casts ──

    public function test_payable_decimal_casts(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '5000.75',
            'amount_paid' => '2500.25',
        ]);

        $ap->refresh();
        $this->assertEquals('5000.75', $ap->amount);
        $this->assertEquals('2500.25', $ap->amount_paid);
    }

    public function test_payable_date_casts(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'due_date' => '2026-12-31',
        ]);

        $ap->refresh();
        $this->assertInstanceOf(Carbon::class, $ap->due_date);
    }

    // ── AccountPayable — Soft Deletes ──

    public function test_payable_soft_delete(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $ap->delete();

        $this->assertNull(AccountPayable::find($ap->id));
        $this->assertNotNull(AccountPayable::withTrashed()->find($ap->id));
    }

    // ── AccountPayable — Static Methods ──

    public function test_statuses_returns_all_cases(): void
    {
        $statuses = AccountPayable::statuses();
        $this->assertIsArray($statuses);
        $this->assertArrayHasKey('pending', $statuses);
        $this->assertArrayHasKey('paid', $statuses);
        $this->assertArrayHasKey('overdue', $statuses);
    }

    // ── AccountReceivable — Relationships ──

    public function test_receivable_belongs_to_customer(): void
    {
        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Test receivable',
            'amount' => '2000.00',
            'amount_paid' => '0.00',
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        $this->assertInstanceOf(Customer::class, $ar->customer);
    }

    public function test_receivable_belongs_to_creator(): void
    {
        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Creator test',
            'amount' => '1500.00',
            'amount_paid' => '0.00',
            'status' => 'pending',
            'due_date' => now()->addDays(15),
        ]);

        $this->assertInstanceOf(User::class, $ar->creator);
    }

    // ── FundTransfer — Relationships ──

    public function test_fund_transfer_belongs_to_bank_account(): void
    {
        $account = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        $transfer = FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $account->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(BankAccount::class, $transfer->bankAccount);
        $this->assertInstanceOf(User::class, $transfer->technician);
    }

    // ── BankStatement — Relationships ──

    public function test_bank_statement_belongs_to_account(): void
    {
        $account = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $statement = BankStatement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $account->id,
        ]);

        $this->assertInstanceOf(BankAccount::class, $statement->bankAccount);
    }

    public function test_bank_statement_has_many_entries(): void
    {
        $account = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $statement = BankStatement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $account->id,
        ]);

        BankStatementEntry::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'bank_statement_id' => $statement->id,
        ]);

        $this->assertCount(5, $statement->entries);
    }
}
