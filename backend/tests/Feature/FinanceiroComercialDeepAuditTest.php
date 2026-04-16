<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountPayableCategory;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Financeiro Deep Audit — AccountReceivable, AccountPayable, Expense.
 * Covers: auth (401), tenant isolation, CRUD, business rules, payment flows.
 */
class FinanceiroComercialDeepAuditTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    private Customer $customerA;

    private AccountPayableCategory $payableCategory;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->adminA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
        ]);
        $this->adminA->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        $this->adminB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
        ]);
        $this->adminB->tenants()->attach($this->tenantB->id, ['is_default' => true]);

        $this->customerA = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->payableCategory = AccountPayableCategory::factory()->create(['tenant_id' => $this->tenantA->id]);

        app()->instance('current_tenant_id', $this->tenantA->id);
        setPermissionsTeamId($this->tenantA->id);
    }

    // ══════════════════════════════════════════════════════════════════════
    // ── CONTAS A RECEBER (AccountReceivable)
    // ══════════════════════════════════════════════════════════════════════

    public function test_unauthenticated_cannot_access_receivables(): void
    {
        $this->getJson('/api/v1/accounts-receivable')->assertUnauthorized();
    }

    public function test_receivable_list_only_shows_own_tenant(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'description' => 'Meu Título',
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenantB->id])->id,
            'description' => 'Título Secreto',
        ]);

        $data = $this->getJson('/api/v1/accounts-receivable')->assertOk()->json('data');

        $descriptions = collect($data)->pluck('description');
        $this->assertTrue($descriptions->contains('Meu Título'));
        $this->assertFalse($descriptions->contains('Título Secreto'));
    }

    public function test_store_receivable_validates_required_fields(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $this->postJson('/api/v1/accounts-receivable', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'description', 'amount', 'due_date']);
    }

    public function test_store_receivable_creates_successfully(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/accounts-receivable', [
            'customer_id' => $this->customerA->id,
            'description' => 'Serviço de Manutenção',
            'amount' => 1500.00,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['description' => 'Serviço de Manutenção']);

        $this->assertDatabaseHas('accounts_receivable', [
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'description' => 'Serviço de Manutenção',
            'status' => AccountReceivable::STATUS_PENDING,
        ]);
    }

    public function test_cross_tenant_receivable_returns_404(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $otherAr = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenantB->id])->id,
        ]);

        // BelongsToTenant global scope filters tenantB record → 404
        $this->getJson("/api/v1/accounts-receivable/{$otherAr->id}")->assertNotFound();
    }

    public function test_pay_receivable_full_amount_marks_as_paid(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'amount' => 500.00,
            'amount_paid' => 0.00,
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $this->postJson("/api/v1/accounts-receivable/{$ar->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('accounts_receivable', [
            'id' => $ar->id,
            'status' => AccountReceivable::STATUS_PAID,
        ]);
    }

    public function test_pay_receivable_partial_amount_marks_as_partial(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'amount' => 1000.00,
            'amount_paid' => 0.00,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $this->postJson("/api/v1/accounts-receivable/{$ar->id}/pay", [
            'amount' => 400.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('accounts_receivable', [
            'id' => $ar->id,
            'status' => AccountReceivable::STATUS_PARTIAL,
        ]);
    }

    public function test_cannot_pay_more_than_remaining_balance(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'amount' => 200.00,
            'amount_paid' => 0.00,
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $this->postJson("/api/v1/accounts-receivable/{$ar->id}/pay", [
            'amount' => 300.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_cannot_pay_cancelled_receivable(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'amount' => 500.00,
            'status' => AccountReceivable::STATUS_CANCELLED,
        ]);

        $this->postJson("/api/v1/accounts-receivable/{$ar->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_delete_receivable_with_payments_is_blocked(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'amount' => 500.00,
            'amount_paid' => 200.00,
            'status' => AccountReceivable::STATUS_PARTIAL,
        ]);

        Payment::create([
            'tenant_id' => $this->tenantA->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $ar->id,
            'amount' => 200.00,
            'payment_method' => 'pix',
            'payment_date' => now(),
            'received_by' => $this->adminA->id,
        ]);

        $this->deleteJson("/api/v1/accounts-receivable/{$ar->id}")->assertStatus(409);

        $this->assertDatabaseHas('accounts_receivable', ['id' => $ar->id]);
    }

    public function test_delete_receivable_without_payments_succeeds(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'amount' => 500.00,
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $this->deleteJson("/api/v1/accounts-receivable/{$ar->id}")->assertNoContent();
        $this->assertSoftDeleted('accounts_receivable', ['id' => $ar->id]);
    }

    public function test_cannot_edit_paid_receivable(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ar = AccountReceivable::factory()->paid()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        $this->putJson("/api/v1/accounts-receivable/{$ar->id}", [
            'description' => 'Tentativa de edição',
        ])->assertUnprocessable();
    }

    public function test_generate_installments_creates_correct_count(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'total' => 900.00,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        $response = $this->postJson('/api/v1/accounts-receivable/installments', [
            'work_order_id' => $wo->id,
            'installments' => 3,
            'first_due_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertCount(3, $response->json());
    }

    public function test_generate_installments_sum_equals_work_order_total(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'total' => 100.01,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        $response = $this->postJson('/api/v1/accounts-receivable/installments', [
            'work_order_id' => $wo->id,
            'installments' => 3,
            'first_due_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertCreated();
        $total = collect($response->json())->sum(fn ($item) => (float) $item['amount']);
        $this->assertEquals(100.01, round($total, 2));
    }

    public function test_generate_installments_requires_minimum_two(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'total' => 500.00,
        ]);

        $this->postJson('/api/v1/accounts-receivable/installments', [
            'work_order_id' => $wo->id,
            'installments' => 1,
            'first_due_date' => now()->addDays(30)->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['installments']);
    }

    public function test_receivables_summary_returns_correct_structure(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'amount' => 1000.00,
            'amount_paid' => 0.00,
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $data = $this->getJson('/api/v1/accounts-receivable-summary')->assertOk()->json();

        $this->assertArrayHasKey('pending', $data);
        $this->assertArrayHasKey('overdue', $data);
        $this->assertArrayHasKey('paid_this_month', $data);
        $this->assertArrayHasKey('billed_this_month', $data);
        $this->assertGreaterThanOrEqual(1000.00, (float) $data['pending']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // ── CONTAS A PAGAR (AccountPayable)
    // ══════════════════════════════════════════════════════════════════════

    public function test_unauthenticated_cannot_access_payables(): void
    {
        $this->getJson('/api/v1/accounts-payable')->assertUnauthorized();
    }

    public function test_payable_list_only_shows_own_tenant(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        AccountPayable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->adminA->id,
            'description' => 'Minha Conta',
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'created_by' => $this->adminB->id,
            'description' => 'Conta Secreta',
        ]);

        $data = $this->getJson('/api/v1/accounts-payable')->assertOk()->json('data');
        $descriptions = collect($data)->pluck('description');
        $this->assertTrue($descriptions->contains('Minha Conta'));
        $this->assertFalse($descriptions->contains('Conta Secreta'));
    }

    public function test_store_payable_validates_required_fields(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $this->postJson('/api/v1/accounts-payable', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description', 'amount', 'due_date']);
    }

    public function test_store_payable_creates_with_correct_tenant(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $this->postJson('/api/v1/accounts-payable', [
            'category_id' => $this->payableCategory->id,
            'description' => 'Aluguel Outubro',
            'amount' => 3500.00,
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertCreated()->assertJsonFragment(['description' => 'Aluguel Outubro']);

        $this->assertDatabaseHas('accounts_payable', [
            'tenant_id' => $this->tenantA->id,
            'description' => 'Aluguel Outubro',
        ]);
    }

    public function test_pay_payable_full_amount_marks_as_paid(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->adminA->id,
            'amount' => 600.00,
            'amount_paid' => 0.00,
            'status' => AccountPayable::STATUS_PENDING,
        ]);

        $this->postJson("/api/v1/accounts-payable/{$ap->id}/pay", [
            'amount' => 600.00,
            'payment_method' => 'transferencia',
            'payment_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('accounts_payable', [
            'id' => $ap->id,
            'status' => AccountPayable::STATUS_PAID,
        ]);
    }

    public function test_cannot_pay_already_liquidated_payable(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->adminA->id,
            'amount' => 300.00,
            'amount_paid' => 300.00,
            'status' => AccountPayable::STATUS_PAID,
        ]);

        $this->postJson("/api/v1/accounts-payable/{$ap->id}/pay", [
            'amount' => 300.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_delete_payable_with_payments_returns_409(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->adminA->id,
            'amount' => 500.00,
            'amount_paid' => 200.00,
            'status' => AccountPayable::STATUS_PARTIAL,
        ]);

        Payment::create([
            'tenant_id' => $this->tenantA->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $ap->id,
            'amount' => 200.00,
            'payment_method' => 'pix',
            'payment_date' => now(),
            'received_by' => $this->adminA->id,
        ]);

        $this->deleteJson("/api/v1/accounts-payable/{$ap->id}")->assertStatus(409);
    }

    public function test_cross_tenant_payable_returns_404(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $otherAp = AccountPayable::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'created_by' => $this->adminB->id,
        ]);

        // BelongsToTenant global scope filters tenantB record → 404
        $this->getJson("/api/v1/accounts-payable/{$otherAp->id}")->assertNotFound();
    }

    public function test_cannot_edit_paid_payable(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->adminA->id,
            'amount' => 400.00,
            'status' => AccountPayable::STATUS_PAID,
        ]);

        $this->putJson("/api/v1/accounts-payable/{$ap->id}", [
            'description' => 'Tentativa de edição',
        ])->assertUnprocessable();
    }

    public function test_payables_summary_returns_correct_structure(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $data = $this->getJson('/api/v1/accounts-payable-summary')->assertOk()->json();

        $this->assertArrayHasKey('pending', $data);
        $this->assertArrayHasKey('overdue', $data);
        $this->assertArrayHasKey('paid_this_month', $data);
    }

    // ══════════════════════════════════════════════════════════════════════
    // ── DESPESAS (Expense)
    // ══════════════════════════════════════════════════════════════════════

    public function test_unauthenticated_cannot_access_expenses(): void
    {
        $this->getJson('/api/v1/expenses')->assertUnauthorized();
    }

    public function test_expense_list_only_shows_own_tenant(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        Expense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->adminA->id,
            'description' => 'Minha Despesa',
        ]);

        Expense::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'created_by' => $this->adminB->id,
            'description' => 'Despesa Secreta',
        ]);

        $data = $this->getJson('/api/v1/expenses')->assertOk()->json('data');
        $descriptions = collect($data)->pluck('description');
        $this->assertTrue($descriptions->contains('Minha Despesa'));
        $this->assertFalse($descriptions->contains('Despesa Secreta'));
    }

    public function test_store_expense_validates_required_fields(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $this->postJson('/api/v1/expenses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description', 'amount', 'expense_date']);
    }

    public function test_store_expense_future_date_rejected(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $this->postJson('/api/v1/expenses', [
            'description' => 'Despesa Futura',
            'amount' => 100.00,
            'expense_date' => now()->addDays(5)->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['expense_date']);
    }

    public function test_store_expense_creates_with_pending_status(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/expenses', [
            'description' => 'Combustível Viagem',
            'amount' => 150.00,
            'expense_date' => now()->subDay()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['status' => ExpenseStatus::PENDING]);

        $this->assertDatabaseHas('expenses', [
            'tenant_id' => $this->tenantA->id,
            'description' => 'Combustível Viagem',
            'status' => ExpenseStatus::PENDING,
        ]);
    }

    public function test_cannot_approve_own_expense(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->adminA->id,
            'status' => ExpenseStatus::PENDING,
        ]);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::APPROVED,
        ])->assertForbidden();
    }

    public function test_expense_invalid_status_transition_rejected(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $other = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
        ]);

        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $other->id,
            'status' => ExpenseStatus::PENDING,
        ]);

        // pending → reimbursed is invalid (skip approved step)
        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::REIMBURSED,
        ])->assertUnprocessable();
    }

    public function test_reject_expense_requires_reason(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $other = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
        ]);

        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $other->id,
            'status' => ExpenseStatus::PENDING,
        ]);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::REJECTED,
        ])->assertUnprocessable();
    }

    public function test_approve_expense_from_other_user_succeeds(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $other = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
        ]);

        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $other->id,
            'status' => ExpenseStatus::PENDING,
        ]);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::APPROVED,
        ])->assertOk();

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => ExpenseStatus::APPROVED,
        ]);
    }

    public function test_cross_tenant_expense_returns_404(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $otherExpense = Expense::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'created_by' => $this->adminB->id,
        ]);

        $this->getJson("/api/v1/expenses/{$otherExpense->id}")->assertNotFound();
    }

    public function test_delete_approved_expense_blocked(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $expense = Expense::factory()->approved()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->adminA->id,
        ]);

        $this->deleteJson("/api/v1/expenses/{$expense->id}")->assertStatus(409);
    }

    public function test_delete_pending_expense_succeeds(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->adminA->id,
            'status' => ExpenseStatus::PENDING,
        ]);

        $this->deleteJson("/api/v1/expenses/{$expense->id}")->assertNoContent();
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_expense_summary_returns_correct_structure(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $data = $this->getJson('/api/v1/expense-summary')->assertOk()->json();

        $this->assertArrayHasKey('pending', $data);
        $this->assertArrayHasKey('approved', $data);
        $this->assertArrayHasKey('month_total', $data);
        $this->assertArrayHasKey('pending_count', $data);
    }

    public function test_batch_status_update_skips_own_expenses(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $ownExpense = Expense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->adminA->id,
            'status' => ExpenseStatus::PENDING,
        ]);

        $other = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
        ]);
        $otherExpense = Expense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $other->id,
            'status' => ExpenseStatus::PENDING,
        ]);

        $response = $this->postJson('/api/v1/expenses/batch-status', [
            'expense_ids' => [$ownExpense->id, $otherExpense->id],
            'status' => ExpenseStatus::APPROVED,
        ])->assertOk();

        $this->assertEquals(1, $response->json('data.processed'));
        $this->assertEquals(1, $response->json('data.skipped'));

        $this->assertDatabaseHas('expenses', [
            'id' => $otherExpense->id,
            'status' => ExpenseStatus::APPROVED,
        ]);
        $this->assertDatabaseHas('expenses', [
            'id' => $ownExpense->id,
            'status' => ExpenseStatus::PENDING,
        ]);
    }

    public function test_batch_reject_requires_reason(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $other = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
        ]);
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $other->id,
            'status' => ExpenseStatus::PENDING,
        ]);

        $this->postJson('/api/v1/expenses/batch-status', [
            'expense_ids' => [$expense->id],
            'status' => ExpenseStatus::REJECTED,
            // rejection_reason missing
        ])->assertUnprocessable();
    }
}
