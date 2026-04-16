<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountReceivableControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        Sanctum::actingAs($this->user, ['*']);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ── INDEX ──────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_receivables(): void
    {
        AccountReceivable::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/accounts-receivable');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'total', 'per_page'],
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_index_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        AccountReceivable::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/accounts-receivable');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
        ]);
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PAID,
        ]);

        $response = $this->getJson('/api/v1/accounts-receivable?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_due_date_range(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'due_date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'due_date' => now()->addDays(60)->format('Y-m-d'),
        ]);

        $response = $this->getJson('/api/v1/accounts-receivable?due_from='.now()->format('Y-m-d').'&due_to='.now()->addDays(30)->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── STORE ──────────────────────────────────────────────────────────────

    public function test_store_creates_receivable(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'description' => 'Serviço de manutenção',
            'amount' => '1500.00',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/accounts-receivable', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.description', 'Serviço de manutenção')
            ->assertJsonPath('data.status', FinancialStatus::PENDING->value);

        $this->assertDatabaseHas('accounts_receivable', [
            'customer_id' => $this->customer->id,
            'description' => 'Serviço de manutenção',
            'tenant_id' => $this->tenant->id,
            'status' => FinancialStatus::PENDING->value,
        ]);
    }

    public function test_store_requires_customer_id(): void
    {
        $response = $this->postJson('/api/v1/accounts-receivable', [
            'description' => 'Teste',
            'amount' => '100.00',
            'due_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_requires_description(): void
    {
        $response = $this->postJson('/api/v1/accounts-receivable', [
            'customer_id' => $this->customer->id,
            'amount' => '100.00',
            'due_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    public function test_store_requires_positive_amount(): void
    {
        $response = $this->postJson('/api/v1/accounts-receivable', [
            'customer_id' => $this->customer->id,
            'description' => 'Teste',
            'amount' => '0',
            'due_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_store_rejects_customer_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/accounts-receivable', [
            'customer_id' => $otherCustomer->id,
            'description' => 'Teste',
            'amount' => '100.00',
            'due_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    // ── SHOW ───────────────────────────────────────────────────────────────

    public function test_show_returns_receivable(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/accounts-receivable/{$ar->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $ar->id);
    }

    public function test_show_rejects_other_tenant_receivable(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/accounts-receivable/{$ar->id}");

        // Should be 403 (tenant ownership check) or 404 (not found)
        $this->assertContains($response->status(), [403, 404]);
    }

    // ── UPDATE ─────────────────────────────────────────────────────────────

    public function test_update_receivable(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Original',
            'status' => FinancialStatus::PENDING,
        ]);

        $response = $this->putJson("/api/v1/accounts-receivable/{$ar->id}", [
            'description' => 'Updated',
            'amount' => $ar->amount,
            'due_date' => $ar->due_date->format('Y-m-d'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'Updated');

        $this->assertDatabaseHas('accounts_receivable', [
            'id' => $ar->id,
            'description' => 'Updated',
        ]);
    }

    public function test_update_blocks_paid_receivable(): void
    {
        $ar = AccountReceivable::factory()->paid()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/accounts-receivable/{$ar->id}", [
            'description' => 'Tentativa',
            'amount' => $ar->amount,
            'due_date' => $ar->due_date->format('Y-m-d'),
        ]);

        $response->assertUnprocessable();
    }

    public function test_update_rejects_other_tenant_receivable(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->putJson("/api/v1/accounts-receivable/{$ar->id}", [
            'description' => 'Hacked',
        ]);

        $this->assertContains($response->status(), [403, 404, 422]);
    }

    // ── DESTROY ────────────────────────────────────────────────────────────

    public function test_destroy_deletes_receivable(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/accounts-receivable/{$ar->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('accounts_receivable', ['id' => $ar->id, 'deleted_at' => null]);
    }

    public function test_destroy_rejects_other_tenant_receivable(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->deleteJson("/api/v1/accounts-receivable/{$ar->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    // ── SUMMARY ────────────────────────────────────────────────────────────

    public function test_summary_returns_financial_totals(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::PENDING,
        ]);

        $response = $this->getJson('/api/v1/accounts-receivable-summary');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['pending', 'overdue', 'paid_this_month', 'total_open']]);
    }
}
