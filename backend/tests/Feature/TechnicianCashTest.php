<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\BankAccount;
use App\Models\TechnicianCashFund;
use App\Models\TechnicianCashTransaction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicianCashTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private User $technician;

    private BankAccount $bankAccount;

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
            'is_active' => true,
        ]);

        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        setPermissionsTeamId($this->tenant->id);
        app()->instance('current_tenant_id', $this->tenant->id);
        $this->user->assignRole('admin');

        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->technician->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 10000,
            'initial_balance' => 10000,
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_credit_creates_fund_and_transaction(): void
    {
        $response = $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $this->technician->id,
            'amount' => 500,
            'description' => 'Verba operacional',
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('technician_cash_funds', [
            'user_id' => $this->technician->id,
            'tenant_id' => $this->tenant->id,
            'balance' => '500.00',
        ]);

        $this->assertDatabaseHas('technician_cash_transactions', [
            'type' => 'credit',
            'amount' => '500.00',
            'balance_after' => '500.00',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_debit_reduces_balance(): void
    {
        // Primeiro, adicionar crédito
        $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $this->technician->id,
            'amount' => 300,
            'description' => 'Verba inicial',
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response = $this->postJson('/api/v1/technician-cash/debit', [
            'user_id' => $this->technician->id,
            'amount' => 100,
            'description' => 'Compra de material',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('technician_cash_funds', [
            'user_id' => $this->technician->id,
            'balance' => '200.00',
        ]);
    }

    public function test_debit_rejects_insufficient_balance(): void
    {
        // Criar fundo com saldo zero
        TechnicianCashFund::getOrCreate($this->technician->id, $this->tenant->id);

        $response = $this->postJson('/api/v1/technician-cash/debit', [
            'user_id' => $this->technician->id,
            'amount' => 100,
            'description' => 'Sem saldo',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_credit_uses_current_tenant_context_after_switch(): void
    {
        $secondTenant = Tenant::factory()->create();
        $this->user->tenants()->attach($secondTenant->id, ['is_default' => false]);
        $this->user->forceFill(['current_tenant_id' => $secondTenant->id])->save();
        app()->instance('current_tenant_id', $secondTenant->id);

        $technician = User::factory()->create([
            'tenant_id' => $secondTenant->id,
            'current_tenant_id' => $secondTenant->id,
            'is_active' => true,
        ]);
        $technician->tenants()->attach($secondTenant->id, ['is_default' => true]);

        $bankAccount2 = BankAccount::factory()->create([
            'tenant_id' => $secondTenant->id,
            'balance' => 10000,
            'initial_balance' => 10000,
        ]);

        $response = $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $technician->id,
            'amount' => 150,
            'description' => 'Adiantamento operacional',
            'payment_method' => 'cash',
            'bank_account_id' => $bankAccount2->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('technician_cash_funds', [
            'user_id' => $technician->id,
            'tenant_id' => $secondTenant->id,
        ]);

        $this->assertDatabaseMissing('technician_cash_funds', [
            'user_id' => $technician->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_credit_rejects_technician_from_other_tenant(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'current_tenant_id' => $foreignTenant->id,
        ]);

        $response = $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $foreignUser->id,
            'amount' => 200,
            'description' => 'Nao permitido',
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_credit_accepts_technician_linked_by_tenant_membership(): void
    {
        $otherTenant = Tenant::factory()->create();
        $sharedTechnician = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);
        $sharedTechnician->tenants()->attach($this->tenant->id, ['is_default' => false]);

        $response = $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $sharedTechnician->id,
            'amount' => 120,
            'description' => 'Credito tecnico compartilhado',
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('technician_cash_funds', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $sharedTechnician->id,
            'balance' => '120.00',
        ]);
    }

    public function test_show_returns_fund_and_transactions(): void
    {
        $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $this->technician->id,
            'amount' => 250,
            'description' => 'Verba teste',
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response = $this->getJson("/api/v1/technician-cash/{$this->technician->id}");

        $response->assertOk()
            ->assertJsonPath('data.fund.user_id', $this->technician->id)
            ->assertJsonPath('data.fund.balance', '250.00');
    }

    public function test_show_rejects_user_from_other_tenant(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'current_tenant_id' => $foreignTenant->id,
        ]);

        $response = $this->getJson("/api/v1/technician-cash/{$foreignUser->id}");

        $response->assertStatus(404);
    }

    public function test_show_without_existing_fund_does_not_create_persistent_record(): void
    {
        $response = $this->getJson("/api/v1/technician-cash/{$this->technician->id}");

        $response->assertOk()
            ->assertJsonPath('data.fund.user_id', $this->technician->id)
            ->assertJsonPath('data.fund.balance', '0.00');

        $this->assertDatabaseMissing('technician_cash_funds', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->technician->id,
        ]);
    }

    public function test_show_accepts_user_linked_by_tenant_membership(): void
    {
        $otherTenant = Tenant::factory()->create();
        $sharedTechnician = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);
        $sharedTechnician->tenants()->attach($this->tenant->id, ['is_default' => false]);

        $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $sharedTechnician->id,
            'amount' => 90,
            'description' => 'Seed para extrato compartilhado',
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
        ])->assertStatus(201);

        $response = $this->getJson("/api/v1/technician-cash/{$sharedTechnician->id}");
        $response->assertOk()
            ->assertJsonPath('data.fund.user_id', $sharedTechnician->id);
    }

    public function test_summary_returns_correct_totals(): void
    {
        // Crédito + Débito
        $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $this->technician->id,
            'amount' => 1000,
            'description' => 'Verba mensal',
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
        ]);
        $this->postJson('/api/v1/technician-cash/debit', [
            'user_id' => $this->technician->id,
            'amount' => 300,
            'description' => 'Compra peças',
        ]);

        $response = $this->getJson('/api/v1/technician-cash-summary');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals(700, $data['total_balance']);
        $this->assertEquals(1000, $data['month_credits']);
        $this->assertEquals(300, $data['month_debits']);
        $this->assertEquals(1, $data['funds_count']);
    }

    public function test_index_lists_all_funds(): void
    {
        TechnicianCashFund::getOrCreate($this->technician->id, $this->tenant->id);

        $response = $this->getJson('/api/v1/technician-cash');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_filters_transactions_by_date_and_pagination(): void
    {
        $fund = TechnicianCashFund::getOrCreate($this->technician->id, $this->tenant->id);

        // Criar transação antiga
        TechnicianCashTransaction::factory()->create([
            'fund_id' => $fund->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'credit',
            'amount' => 100,
            'balance_after' => 100,
            'transaction_date' => now()->subDays(10)->toDateString(),
            'description' => 'Antiga',
        ]);
        // Criar transação recente
        TechnicianCashTransaction::factory()->create([
            'fund_id' => $fund->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'debit',
            'amount' => 50,
            'balance_after' => 50,
            'transaction_date' => now()->toDateString(),
            'description' => 'Recente',
        ]);

        // Filtro Data
        $response = $this->getJson("/api/v1/technician-cash/{$this->technician->id}?date_from=".now()->toDateString());
        $response->assertOk();
        $this->assertCount(1, $response->json('data.transactions.data'));
        $this->assertEquals('Recente', $response->json('data.transactions.data.0.description'));

        // Paginação
        $responsePag = $this->getJson("/api/v1/technician-cash/{$this->technician->id}?per_page=1");
        $responsePag->assertOk();
        $this->assertEquals(1, $responsePag->json('data.transactions.per_page'));
        $this->assertEquals(2, $responsePag->json('data.transactions.total'));
    }

    public function test_my_fund_returns_authenticated_user_fund(): void
    {
        $fund = TechnicianCashFund::getOrCreate($this->user->id, $this->tenant->id);
        $fund->forceFill(['balance' => '55.00'])->save();

        $response = $this->getJson('/api/v1/technician-cash/my-fund');

        $response->assertOk()
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.balance', '55.00');
    }

    public function test_my_fund_without_existing_record_creates_persistent_fund(): void
    {
        $response = $this->getJson('/api/v1/technician-cash/my-fund');

        $response->assertOk()
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.balance', '0.00');

        $this->assertDatabaseHas('technician_cash_funds', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'balance' => '0.00',
        ]);
    }

    public function test_request_funds_creates_pending_request_for_authenticated_user(): void
    {
        $response = $this->postJson('/api/v1/technician-cash/request-funds', [
            'amount' => 120.75,
            'reason' => 'Caixa para atendimento externo',
            'payment_method' => 'cash',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('technician_fund_requests', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
    }
}
