<?php

namespace Tests\Unit\Services;

use App\Enums\FinancialStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementEntry;
use App\Models\Customer;
use App\Models\FundTransfer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AccountPayableReceivableServiceTest extends TestCase
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

    public function test_create_payable_with_pending_status(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
            'amount' => '5000.00',
            'due_date' => now()->addDays(30),
        ]);

        $this->assertEquals(FinancialStatus::PENDING, $ap->status);
    }

    public function test_payable_status_becomes_overdue(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'due_date' => now()->subDays(5),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::OVERDUE, $ap->status);
    }

    public function test_payable_status_becomes_paid(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
            'amount' => '1000.00',
            'amount_paid' => '1000.00',
            'due_date' => now()->addDays(30),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::PAID, $ap->status);
    }

    public function test_payable_status_becomes_partial(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
            'amount' => '1000.00',
            'amount_paid' => '500.00',
            'due_date' => now()->addDays(30),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::PARTIAL, $ap->status);
    }

    public function test_payable_cancelled_not_changed_by_recalculate(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::CANCELLED,
            'amount' => '1000.00',
            'amount_paid' => '1000.00',
            'due_date' => now()->addDays(30),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::CANCELLED, $ap->status);
    }

    // ── AccountReceivable ──

    public function test_create_receivable(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'description' => 'Serviço calibração',
            'amount' => '8000.00',
            'amount_paid' => '0.00',
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        $this->assertEquals('8000.00', $ar->amount);
        $this->assertEquals('pending', $ar->status->value ?? $ar->status);
    }

    // ── FundTransfer ──

    public function test_create_fund_transfer(): void
    {
        $from = BankAccount::factory()->create(['tenant_id' => $this->tenant->id, 'balance' => 10000]);
        $to = BankAccount::factory()->create(['tenant_id' => $this->tenant->id, 'balance' => 5000]);

        $transfer = FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $from->id,
            'amount' => '3000.00',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals('3000.00', $transfer->amount);
    }

    // ── BankStatement ──

    public function test_bank_statement_with_entries(): void
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
