<?php

namespace Tests\Feature\Financial;

use App\Enums\FinancialStatus;
use App\Enums\FundTransferStatus;
use App\Models\AccountPayable;
use App\Models\BankAccount;
use App\Models\FundTransfer;
use App\Models\Role;
use App\Models\TechnicianCashFund;
use App\Models\TechnicianCashTransaction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FundTransferTest extends TestCase
{
    protected $user;

    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        setPermissionsTeamId($this->tenant->id);
        app()->instance('current_tenant_id', $this->tenant->id);

        // Create permissions first (Permission::all() is empty without seeders)
        $permNames = [
            'financial.fund_transfer.view',
            'financial.fund_transfer.create',
            'financial.fund_transfer.cancel',
        ];
        foreach ($permNames as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);
        $role->syncPermissions($permNames);
        $this->user->assignRole($role);
    }

    public function test_can_create_fund_transfer(): void
    {
        $technician = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 5000.00,
        ]);

        $data = [
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $technician->id,
            'amount' => 1000.00,
            'transfer_date' => now()->toDateString(),
            'payment_method' => 'pix',
            'description' => 'Adiantamento de viagem',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/fund-transfers', $data);

        $response->assertCreated();

        // 1. Verify FundTransfer created
        $this->assertDatabaseHas('fund_transfers', [
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $technician->id,
            'amount' => 1000.00,
            'status' => 'completed',
        ]);

        // 2. Verify BankAccount balance updated
        $this->assertEquals(4000.00, $bankAccount->fresh()->balance);

        // 3. Verify AccountPayable created and paid
        $this->assertDatabaseHas('accounts_payable', [
            'description' => "Adiantamento Técnico: {$technician->name} — Adiantamento de viagem",
            'amount' => 1000.00,
            'amount_paid' => 1000.00,
            'status' => 'paid',
        ]);

        // 4. Verify TechnicianCashFund credited
        $fund = TechnicianCashFund::where('user_id', $technician->id)->first();
        $this->assertNotNull($fund);
        $this->assertEquals(1000.00, $fund->balance);

        // 5. Verify TechnicianCashTransaction created
        $this->assertDatabaseHas('technician_cash_transactions', [
            'type' => 'credit',
            'amount' => 1000.00,
        ]);
    }

    public function test_cannot_transfer_with_insufficient_funds(): void
    {
        $technician = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 100.00, // Insufficient
        ]);

        $data = [
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $technician->id,
            'amount' => 1000.00,
            'transfer_date' => now()->toDateString(),
            'payment_method' => 'pix',
            'description' => 'Fail transfer',
        ];

        $this->actingAs($this->user)
            ->postJson('/api/v1/fund-transfers', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_can_cancel_fund_transfer(): void
    {
        // Setup initial state (transfer already made)
        $technician = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 4000.00, // Post-transfer balance
        ]);

        // Create the transfer manually to simulate previous state
        $transfer = FundTransfer::factory()->create([
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $technician->id,
            'amount' => 1000.00,
            'status' => 'completed',
            'tenant_id' => $this->tenant->id,
        ]);

        // Create associated AP
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => 1000.00,
            'amount_paid' => 1000.00,
            'status' => 'paid',
        ]);

        $transfer->account_payable_id = $ap->id;
        $transfer->save();

        // Create Cash Fund and Transaction
        $fund = TechnicianCashFund::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $technician->id,
            'balance' => 1000.00,
        ]);

        TechnicianCashTransaction::create([
            'tenant_id' => $this->tenant->id,
            'fund_id' => $fund->id,
            'type' => 'credit',
            'amount' => 1000.00,
            'balance_after' => 1000.00,
            'description' => 'Original transfer',
            'transaction_date' => now()->toDateString(),
        ]);

        // Act: Cancel the transfer
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/fund-transfers/{$transfer->id}/cancel");

        $response->assertOk();

        // Verify:
        // 1. Transfer status cancelled
        $this->assertEquals(FundTransferStatus::CANCELLED, $transfer->fresh()->status);

        // 2. Bank Account refunded
        $this->assertEquals(5000.00, $bankAccount->fresh()->balance);

        // 3. AP cancelled
        $this->assertEquals(FinancialStatus::CANCELLED, $ap->fresh()->status);

        // 4. Technician Fund debited
        $this->assertEquals(0.00, $fund->fresh()->balance);

        // 5. Reversal transaction created
        $this->assertDatabaseHas('technician_cash_transactions', [
            'type' => 'debit', // Reversal is a debit
            'amount' => 1000.00,
            'amount' => 1000.00,
            // 'description' will be dynamic, so we skip exact match or use like logic, but assertDatabaseHas matches exact.
            // Let's use the exact string that controller generates.
            'description' => "Cancelamento de transferência #{$transfer->id}: {$transfer->description}",
        ]);
    }

    public function test_can_list_transfers(): void
    {
        FundTransfer::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/fund-transfers')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_summary_calculation(): void
    {
        $tech1 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $tech2 = User::factory()->create(['tenant_id' => $this->tenant->id]);

        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'to_user_id' => $tech1->id,
            'amount' => 1000.00,
            'transfer_date' => now(),
        ]);

        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'to_user_id' => $tech2->id,
            'amount' => 500.00,
            'transfer_date' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/fund-transfers/summary');

        $response->assertOk()
            ->assertJson([
                'month_total' => 1500.00,
                'total_all' => 1500.00,
            ]);
    }
}
