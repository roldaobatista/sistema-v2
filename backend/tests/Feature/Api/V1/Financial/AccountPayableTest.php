<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountPayableCategory;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountPayableTest extends TestCase
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
        AccountPayable::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/accounts-payable');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_filters_by_status(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
        ]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PAID,
            'amount_paid' => 100,
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/accounts-payable?status=pending');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_multiple_statuses(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING,
        ]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::OVERDUE,
            'due_date' => now()->subDays(5),
        ]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PAID,
            'amount_paid' => 100,
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/accounts-payable?status=pending,overdue');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_filters_by_due_date_range(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'due_date' => '2026-03-15',
        ]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'due_date' => '2026-04-15',
        ]);

        $response = $this->getJson('/api/v1/accounts-payable?due_from=2026-03-01&due_to=2026-03-31');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_search(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Pagamento de aluguel escritorio',
        ]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Compra de materiais',
        ]);

        $response = $this->getJson('/api/v1/accounts-payable?search=aluguel');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_does_not_show_other_tenant_data(): void
    {
        $otherTenant = Tenant::factory()->create();
        AccountPayable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $this->user->id,
        ]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/accounts-payable');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    // ── STORE ──────────────────────────────────────────────────

    public function test_store_creates_account_payable(): void
    {
        $category = AccountPayableCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $payload = [
            'category_id' => $category->id,
            'description' => 'Conta de luz',
            'amount' => 350.50,
            'due_date' => '2026-04-10',
        ];

        $response = $this->postJson('/api/v1/accounts-payable', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.description', 'Conta de luz');

        $this->assertDatabaseHas('accounts_payable', [
            'tenant_id' => $this->tenant->id,
            'description' => 'Conta de luz',
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PENDING->value,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/accounts-payable', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'description', 'amount', 'due_date']);
    }

    public function test_store_validates_amount_minimum(): void
    {
        $category = AccountPayableCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/accounts-payable', [
            'category_id' => $category->id,
            'description' => 'Teste',
            'amount' => 0,
            'due_date' => '2026-04-10',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_store_with_optional_fields(): void
    {
        $category = AccountPayableCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $payload = [
            'description' => 'Fornecedor XYZ',
            'amount' => 1200.00,
            'due_date' => '2026-04-15',
            'category_id' => $category->id,
            'payment_method' => 'boleto',
            'notes' => 'Nota fiscal 12345',
        ];

        $response = $this->postJson('/api/v1/accounts-payable', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('accounts_payable', [
            'category_id' => $category->id,
            'payment_method' => 'boleto',
            'notes' => 'Nota fiscal 12345',
        ]);
    }

    // ── SHOW ──────────────────────────────────────────────────

    public function test_show_returns_record_with_relations(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/accounts-payable/{$record->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'description', 'amount', 'due_date', 'status']]);
    }

    public function test_show_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $record = AccountPayable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/accounts-payable/{$record->id}");

        $response->assertStatus(404);
    }

    // ── UPDATE ──────────────────────────────────────────────────

    public function test_update_modifies_fields(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Original',
            'status' => FinancialStatus::PENDING,
        ]);

        $response = $this->putJson("/api/v1/accounts-payable/{$record->id}", [
            'description' => 'Atualizado',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'Atualizado');
    }

    public function test_update_rejects_cancelled_record(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::CANCELLED,
        ]);

        $response = $this->putJson("/api/v1/accounts-payable/{$record->id}", [
            'description' => 'Novo',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Título cancelado ou pago não pode ser editado']);
    }

    public function test_update_rejects_paid_record(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::PAID,
            'amount' => 100,
            'amount_paid' => 100,
            'paid_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/accounts-payable/{$record->id}", [
            'description' => 'Novo',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_rejects_amount_below_already_paid(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING,
        ]);

        // Create a payment to satisfy payments()->exists() and set partial status
        $this->postJson("/api/v1/accounts-payable/{$record->id}/pay", [
            'amount' => 500,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        $record->refresh();
        $this->assertEquals(FinancialStatus::PARTIAL, $record->status);

        $response = $this->putJson("/api/v1/accounts-payable/{$record->id}", [
            'amount' => 300,
        ]);

        $response->assertStatus(422);
    }

    // ── DESTROY ──────────────────────────────────────────────────

    public function test_destroy_deletes_record(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/accounts-payable/{$record->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('accounts_payable', ['id' => $record->id]);
    }

    public function test_destroy_rejects_when_payments_exist(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 1000,
            'amount_paid' => 0,
        ]);

        Payment::create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $record->id,
            'received_by' => $this->user->id,
            'amount' => 200,
            'payment_method' => 'pix',
            'payment_date' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/accounts-payable/{$record->id}");

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Não é possível excluir título com pagamentos vinculados']);
    }

    // ── PAY ──────────────────────────────────────────────────

    public function test_pay_creates_payment(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 500,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING,
        ]);

        $response = $this->postJson("/api/v1/accounts-payable/{$record->id}/pay", [
            'amount' => 200,
            'payment_method' => 'pix',
            'payment_date' => '2026-03-09',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('payments', [
            'payable_type' => AccountPayable::class,
            'payable_id' => $record->id,
            'amount' => '200.00',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_pay_rejects_cancelled_record(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => FinancialStatus::CANCELLED,
        ]);

        $response = $this->postJson("/api/v1/accounts-payable/{$record->id}/pay", [
            'amount' => 100,
            'payment_method' => 'pix',
            'payment_date' => '2026-03-09',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Título cancelado não pode receber baixa']);
    }

    public function test_pay_rejects_amount_exceeding_remaining(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 500,
            'amount_paid' => 400,
            'status' => FinancialStatus::PARTIAL,
        ]);

        $response = $this->postJson("/api/v1/accounts-payable/{$record->id}/pay", [
            'amount' => 200,
            'payment_method' => 'pix',
            'payment_date' => '2026-03-09',
        ]);

        $response->assertStatus(422);
    }

    public function test_pay_validates_required_fields(): void
    {
        $record = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/accounts-payable/{$record->id}/pay", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'payment_method', 'payment_date']);
    }

    // ── SUMMARY ──────────────────────────────────────────────────

    public function test_summary_returns_financial_totals(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING,
        ]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 500,
            'amount_paid' => 0,
            'status' => FinancialStatus::OVERDUE,
            'due_date' => now()->subDays(5),
        ]);

        $response = $this->getJson('/api/v1/accounts-payable-summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['pending', 'overdue', 'recorded_this_month', 'paid_this_month', 'total_open'],
            ]);
    }

    // ── EXPORT ──────────────────────────────────────────────────

    public function test_export_returns_csv(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get('/api/v1/accounts-payable-export');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    // ── AUTH ──────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        // Reset authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/accounts-payable');

        $response->assertUnauthorized();
    }
}
