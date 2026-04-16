<?php

namespace Tests\Feature\Api;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\AccountPayable;
use App\Models\AccountPayableCategory;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FinancialControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
    }

    // ── Account Payable ──

    public function test_index_payables(): void
    {
        AccountPayable::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/accounts-payable');
        $response->assertOk();
    }

    public function test_store_payable(): void
    {
        $category = AccountPayableCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->user)->postJson('/api/v1/accounts-payable', [
            'category_id' => $category->id,
            'description' => 'Fornecedor XYZ',
            'amount' => '5000.00',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertCreated();
    }

    public function test_show_payable(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/accounts-payable/{$ap->id}");
        $response->assertOk();
    }

    public function test_update_payable(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/accounts-payable/{$ap->id}", [
            'description' => 'Atualizado',
        ]);

        $response->assertOk();
    }

    public function test_destroy_payable(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/accounts-payable/{$ap->id}");
        $response->assertNoContent();
    }

    // ── Account Receivable ──

    public function test_index_receivables(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/accounts-receivable');
        $response->assertOk();
    }

    public function test_store_receivable(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/accounts-receivable', [
            'customer_id' => $this->customer->id,
            'description' => 'Serviço calibração',
            'amount' => '8000.00',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertCreated();
    }

    // ── Bank Account ──

    public function test_index_bank_accounts(): void
    {
        BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/bank-accounts');
        $response->assertOk();
    }

    // ── Fund Transfer ──

    public function test_store_fund_transfer(): void
    {
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 10000,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/fund-transfers', [
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'amount' => '3000.00',
            'transfer_date' => now()->format('Y-m-d'),
            'payment_method' => 'pix',
            'description' => 'Transferência teste',
        ]);

        $response->assertCreated();
    }

    // ── Unauthenticated ──

    public function test_unauthenticated_payable_returns_401(): void
    {
        $response = $this->getJson('/api/v1/accounts-payable');
        $response->assertUnauthorized();
    }

    // ── Tenant Isolation ──

    public function test_payable_tenant_isolation(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $other = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'current_tenant_id' => $other->id]);
        $otherUser->tenants()->attach($other->id, ['is_default' => true]);
        setPermissionsTeamId($other->id);
        $otherUser->assignRole('admin');

        app()->instance('current_tenant_id', $other->id);
        $response = $this->actingAs($otherUser)->getJson('/api/v1/accounts-payable');
        $this->assertEmpty($response->json('data'));
    }
}
