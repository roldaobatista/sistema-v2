<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Enums\FundTransferStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\BankAccount;
use App\Models\FundTransfer;
use App\Models\TechnicianCashFund;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FundTransferTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── INDEX ──────────────────────────────────────────────────

    public function test_index_returns_paginated_list(): void
    {
        FundTransfer::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'bank_account_id' => BankAccount::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'to_user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/fund-transfers');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_filters_by_status(): void
    {
        $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => FundTransferStatus::COMPLETED->value,
        ]);
        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => FundTransferStatus::CANCELLED->value,
        ]);

        $response = $this->getJson('/api/v1/fund-transfers?status=completed');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_to_user_id(): void
    {
        $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $tech->id,
            'created_by' => $this->user->id,
        ]);
        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/fund-transfers?to_user_id={$tech->id}");

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_date_range(): void
    {
        $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'transfer_date' => '2026-01-15',
        ]);
        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'transfer_date' => '2026-03-05',
        ]);

        $response = $this->getJson('/api/v1/fund-transfers?date_from=2026-03-01&date_to=2026-03-31');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    // ── SHOW ──────────────────────────────────────────────────

    public function test_show_returns_transfer_details(): void
    {
        $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $transfer = FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
            'description' => 'Test transfer',
        ]);

        $response = $this->getJson("/api/v1/fund-transfers/{$transfer->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $transfer->id)
            ->assertJsonPath('data.amount', '500.00')
            ->assertJsonPath('data.description', 'Test transfer')
            ->assertJsonStructure([
                'data' => ['id', 'tenant_id', 'bank_account_id', 'to_user_id', 'amount', 'transfer_date', 'payment_method', 'description', 'status'],
            ]);
    }

    public function test_show_blocks_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $bankAccount = BankAccount::factory()->create(['tenant_id' => $otherTenant->id]);
        $transfer = FundTransfer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/fund-transfers/{$transfer->id}");

        $response->assertStatus(404);
    }

    // ── STORE ─────────────────────────────────────────────────

    public function test_store_creates_transfer_with_full_flow(): void
    {
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 10000.00,
            'is_active' => true,
        ]);
        $tech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $payload = [
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $tech->id,
            'amount' => 500.00,
            'transfer_date' => '2026-03-09',
            'payment_method' => 'pix',
            'description' => 'Adiantamento para visita',
        ];

        $response = $this->postJson('/api/v1/fund-transfers', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', '500.00')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payment_method', 'pix');

        // Verify FundTransfer record exists
        $this->assertDatabaseHas('fund_transfers', [
            'tenant_id' => $this->tenant->id,
            'to_user_id' => $tech->id,
            'amount' => 500.00,
            'status' => 'completed',
        ]);

        // Verify AccountPayable was created
        $this->assertDatabaseHas('accounts_payable', [
            'tenant_id' => $this->tenant->id,
            'amount' => 500.00,
            'status' => FinancialStatus::PAID->value,
        ]);

        // Verify bank account balance was decremented
        $bankAccount->refresh();
        $this->assertEquals('9500.00', $bankAccount->balance);

        // Verify TechnicianCashFund was credited
        $fund = TechnicianCashFund::where('user_id', $tech->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();
        $this->assertNotNull($fund);
        $this->assertEquals('500.00', $fund->balance);
    }

    public function test_store_fails_with_insufficient_bank_balance(): void
    {
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 100.00,
            'is_active' => true,
        ]);

        $payload = [
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'amount' => 500.00,
            'transfer_date' => '2026-03-09',
            'payment_method' => 'pix',
            'description' => 'Valor acima do saldo',
        ];

        $response = $this->postJson('/api/v1/fund-transfers', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_store_fails_with_technician_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 10000.00,
            'is_active' => true,
        ]);

        $payload = [
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $otherUser->id,
            'amount' => 500.00,
            'transfer_date' => '2026-03-09',
            'payment_method' => 'pix',
            'description' => 'Tech de outro tenant',
        ];

        $response = $this->postJson('/api/v1/fund-transfers', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_user_id']);
    }

    public function test_store_validation_requires_all_fields(): void
    {
        $response = $this->postJson('/api/v1/fund-transfers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'bank_account_id',
                'to_user_id',
                'amount',
                'transfer_date',
                'payment_method',
                'description',
            ]);
    }

    public function test_store_validation_rejects_zero_amount(): void
    {
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 10000.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/fund-transfers', [
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'amount' => 0,
            'transfer_date' => '2026-03-09',
            'payment_method' => 'pix',
            'description' => 'Zero amount',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    // ── CANCEL ────────────────────────────────────────────────

    public function test_cancel_reverses_transfer(): void
    {
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 10000.00,
            'is_active' => true,
        ]);
        $tech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        // First create a transfer
        $createPayload = [
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $tech->id,
            'amount' => 500.00,
            'transfer_date' => '2026-03-09',
            'payment_method' => 'pix',
            'description' => 'Transfer to cancel',
        ];
        $createResponse = $this->postJson('/api/v1/fund-transfers', $createPayload);
        $createResponse->assertStatus(201);
        $transferId = $createResponse->json('data.id');

        // Now cancel it
        $response = $this->postJson("/api/v1/fund-transfers/{$transferId}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // Verify status is cancelled
        $this->assertDatabaseHas('fund_transfers', [
            'id' => $transferId,
            'status' => 'cancelled',
        ]);

        // Verify bank account balance was refunded
        $bankAccount->refresh();
        $this->assertEquals('10000.00', $bankAccount->balance);
    }

    public function test_cancel_already_cancelled_returns_422(): void
    {
        $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $transfer = FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => FundTransferStatus::CANCELLED->value,
        ]);

        $response = $this->postJson("/api/v1/fund-transfers/{$transfer->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_cancel_blocks_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $bankAccount = BankAccount::factory()->create(['tenant_id' => $otherTenant->id]);
        $transfer = FundTransfer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/fund-transfers/{$transfer->id}/cancel");

        $response->assertStatus(404);
    }

    // ── SUMMARY ───────────────────────────────────────────────

    public function test_summary_returns_aggregated_data(): void
    {
        $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $tech->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => FundTransferStatus::COMPLETED->value,
        ]);
        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $tech->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => FundTransferStatus::COMPLETED->value,
        ]);

        $response = $this->getJson('/api/v1/fund-transfers/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['month_total', 'total_all', 'by_technician'],
            ]);

        // Sum should be 1500
        $data = $response->json('data');
        $this->assertEquals(1500, (float) $data['month_total']);
        $this->assertEquals(1500, (float) $data['total_all']);
        $this->assertNotEmpty($data['by_technician']);
    }

    public function test_summary_excludes_cancelled_transfers(): void
    {
        $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        FundTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => FundTransferStatus::CANCELLED->value,
        ]);

        $response = $this->getJson('/api/v1/fund-transfers/summary');

        $response->assertOk();
        $this->assertEquals('0.00', $response->json('data.month_total'));
    }

    // ── AUTH ──────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        // Reset auth
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/fund-transfers');

        $response->assertStatus(401);
    }
}
