<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\TechnicianCashFund;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

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

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
        Gate::before(fn () => true);
    }

    /**
     * Helper to create an Expense with status (status is guarded, needs forceFill).
     */
    private function createExpense(array $attributes = [], string $status = ExpenseStatus::PENDING->value): Expense
    {
        $expense = new Expense(array_diff_key($attributes, ['status' => 1, 'rejection_reason' => 1]));
        $forceData = ['status' => $status];
        if (isset($attributes['rejection_reason'])) {
            $forceData['rejection_reason'] = $attributes['rejection_reason'];
        }
        $expense->forceFill($forceData);
        $expense->save();

        return $expense;
    }

    public function test_store_rejects_category_from_other_tenant(): void
    {
        $foreignCategory = ExpenseCategory::create([
            'tenant_id' => Tenant::factory()->create()->id,
            'name' => 'Viagem',
            'color' => '#cccccc',
            'active' => true,
        ]);

        $response = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $foreignCategory->id,
            'description' => 'Taxi',
            'amount' => 120,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expense_category_id']);
    }

    public function test_store_creates_with_pending_status(): void
    {
        $response = $this->postJson('/api/v1/expenses', [
            'description' => 'Material de escritorio',
            'amount' => 55.90,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', ExpenseStatus::PENDING->value);

        $this->assertDatabaseHas('expenses', [
            'id' => $response->json('data.id'),
            'status' => ExpenseStatus::PENDING->value,
        ]);
    }

    public function test_show_blocks_cross_tenant_expense(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignExpense = $this->createExpense([
            'tenant_id' => $otherTenant->id,
            'created_by' => User::factory()->create(['tenant_id' => $otherTenant->id, 'current_tenant_id' => $otherTenant->id])->id,
            'description' => 'Despesa alheia',
            'amount' => 100,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->getJson("/api/v1/expenses/{$foreignExpense->id}");
        $response->assertStatus(404);
    }

    public function test_update_blocks_approved_expense(): void
    {
        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Aluguel',
            'amount' => 300,
            'expense_date' => now()->toDateString(),
        ], ExpenseStatus::APPROVED->value);

        $this->putJson("/api/v1/expenses/{$expense->id}", [
            'description' => 'Tentativa de alterar',
        ])->assertStatus(422);
    }

    public function test_delete_blocks_cross_tenant_expense(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignExpense = $this->createExpense([
            'tenant_id' => $otherTenant->id,
            'created_by' => User::factory()->create(['tenant_id' => $otherTenant->id, 'current_tenant_id' => $otherTenant->id])->id,
            'description' => 'Despesa alheia',
            'amount' => 50,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->deleteJson("/api/v1/expenses/{$foreignExpense->id}");
        $response->assertStatus(404);
    }

    public function test_expense_returns_os_identifier_when_linked_to_work_order(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'BL-7788',
            'number' => 'OS-000123',
        ]);

        $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'description' => 'Combustivel da visita',
            'amount' => 150.00,
            'expense_date' => now()->toDateString(),
        ]);

        $this->getJson('/api/v1/expenses')
            ->assertOk()
            ->assertJsonPath('data.0.work_order.os_number', 'BL-7788');
    }

    public function test_reimbursed_expense_returns_value_to_technician_cash(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Adiantamento de campo',
            'amount' => 200.00,
            'expense_date' => now()->toDateString(),
            'affects_technician_cash' => true,
        ]);

        // Act as approver to avoid self-approval block
        Sanctum::actingAs($approver, ['*']);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::APPROVED->value,
        ])->assertOk();

        $fund = TechnicianCashFund::where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($fund);
        $this->assertSame(-200.0, (float) $fund->balance);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::REIMBURSED->value,
        ])->assertOk();

        $this->assertSame(0.0, (float) $fund->fresh()->balance);
    }

    public function test_rejecting_expense_requires_and_persists_reason(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Compra sem comprovante',
            'amount' => 90.00,
            'expense_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($approver, ['*']);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::REJECTED->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::REJECTED->value,
            'rejection_reason' => 'Documento fiscal ausente',
        ])->assertOk()
            ->assertJsonPath('data.status', ExpenseStatus::REJECTED->value)
            ->assertJsonPath('data.rejection_reason', 'Documento fiscal ausente');
    }

    public function test_update_status_blocks_cross_tenant_expense(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignExpense = $this->createExpense([
            'tenant_id' => $otherTenant->id,
            'created_by' => User::factory()->create(['tenant_id' => $otherTenant->id, 'current_tenant_id' => $otherTenant->id])->id,
            'description' => 'Despesa alheia',
            'amount' => 100,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->putJson("/api/v1/expenses/{$foreignExpense->id}/status", [
            'status' => ExpenseStatus::APPROVED->value,
        ]);
        $response->assertStatus(404);
    }

    public function test_destroy_category_blocked_when_expenses_exist(): void
    {
        $category = ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alimentacao',
            'color' => '#ff0000',
            'active' => true,
        ]);

        $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'expense_category_id' => $category->id,
            'created_by' => $this->user->id,
            'description' => 'Almoço',
            'amount' => 35,
            'expense_date' => now()->toDateString(),
        ]);

        $this->deleteJson("/api/v1/expense-categories/{$category->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Categoria possui despesas vinculadas. Remova ou reclassifique antes de excluir.']);
    }

    public function test_store_category_rejects_duplicate_name_same_tenant(): void
    {
        ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Transporte',
            'color' => '#00ff00',
            'active' => true,
        ]);

        $this->postJson('/api/v1/expense-categories', [
            'name' => 'Transporte',
            'color' => '#0000ff',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_category_rejects_duplicate_name_same_tenant(): void
    {
        $cat1 = ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alimentacao',
            'color' => '#ff0000',
            'active' => true,
        ]);

        $cat2 = ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Transporte',
            'color' => '#00ff00',
            'active' => true,
        ]);

        $this->putJson("/api/v1/expense-categories/{$cat2->id}", [
            'name' => 'Alimentacao',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Same name to itself should be allowed
        $this->putJson("/api/v1/expense-categories/{$cat1->id}", [
            'name' => 'Alimentacao',
        ])->assertOk();
    }

    public function test_update_category_blocks_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignCategory = ExpenseCategory::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Hospedagem',
            'color' => '#aaaaaa',
            'active' => true,
        ]);

        $response = $this->putJson("/api/v1/expense-categories/{$foreignCategory->id}", [
            'name' => 'Tentativa',
        ]);
        $response->assertStatus(404);
    }

    public function test_summary_returns_correct_totals(): void
    {
        $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Pendente 1',
            'amount' => 100.00,
            'expense_date' => now()->toDateString(),
        ]);
        $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Aprovada 1',
            'amount' => 200.00,
            'expense_date' => now()->toDateString(),
        ], ExpenseStatus::APPROVED->value);
        $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Rejeitada 1',
            'amount' => 50.00,
            'expense_date' => now()->toDateString(),
        ], ExpenseStatus::REJECTED->value);

        $response = $this->getJson('/api/v1/expense-summary')
            ->assertOk();

        $this->assertEquals(100.0, $response->json('data.pending'));
        $this->assertEquals(200.0, $response->json('data.approved'));
        // month_total should exclude rejected
        $this->assertEquals(300.0, $response->json('data.month_total'));
    }

    public function test_delete_blocked_for_approved_expense(): void
    {
        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Aprovada',
            'amount' => 500,
            'expense_date' => now()->toDateString(),
        ], ExpenseStatus::APPROVED->value);

        $this->deleteJson("/api/v1/expenses/{$expense->id}")
            ->assertStatus(409);
    }

    public function test_rejected_expense_clears_approved_by(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Para rejeitar',
            'amount' => 80.00,
            'expense_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($approver, ['*']);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::REJECTED->value,
            'rejection_reason' => 'Sem comprovante',
        ])->assertOk();

        $this->assertNull($expense->fresh()->approved_by);
    }

    public function test_rejected_expense_can_be_resubmitted_as_pending(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Despesa para resubmeter',
            'amount' => 120.00,
            'expense_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($approver, ['*']);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::REJECTED->value,
            'rejection_reason' => 'Falta comprovante',
        ])->assertOk();

        $this->assertSame(ExpenseStatus::REJECTED->value, $expense->fresh()->status->value);

        // Resubmit as pending
        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::PENDING->value,
        ])->assertOk();

        $this->assertSame(ExpenseStatus::PENDING->value, $expense->fresh()->status->value);
        $this->assertNull($expense->fresh()->rejection_reason);
    }

    public function test_store_defaults_affects_net_value_to_true(): void
    {
        $response = $this->postJson('/api/v1/expenses', [
            'description' => 'Despesa sem flag explicito',
            'amount' => 75.00,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('expenses', [
            'id' => $response->json('data.id'),
            'affects_net_value' => true,
        ]);
    }

    public function test_store_persists_affects_net_value_false(): void
    {
        $response = $this->postJson('/api/v1/expenses', [
            'description' => 'Despesa nao dedutivel',
            'amount' => 120.00,
            'expense_date' => now()->toDateString(),
            'affects_net_value' => false,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('expenses', [
            'id' => $response->json('data.id'),
            'affects_net_value' => false,
        ]);
    }

    public function test_update_toggles_affects_net_value(): void
    {
        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Despesa editavel',
            'amount' => 80,
            'expense_date' => now()->toDateString(),
            'affects_net_value' => true,
        ]);

        $this->putJson("/api/v1/expenses/{$expense->id}", [
            'affects_net_value' => false,
        ])->assertOk();

        $this->assertFalse((bool) $expense->fresh()->affects_net_value);
    }

    public function test_update_reapplies_category_defaults_when_category_changes_without_explicit_flags(): void
    {
        $category = ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Combustivel',
            'color' => '#123456',
            'active' => true,
            'default_affects_net_value' => false,
            'default_affects_technician_cash' => true,
        ]);

        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Despesa editavel por categoria',
            'amount' => 80,
            'expense_date' => now()->toDateString(),
            'affects_net_value' => true,
            'affects_technician_cash' => false,
        ]);

        $this->putJson("/api/v1/expenses/{$expense->id}", [
            'expense_category_id' => $category->id,
        ])->assertOk();

        $expense->refresh();

        $this->assertFalse((bool) $expense->affects_net_value);
        $this->assertTrue((bool) $expense->affects_technician_cash);
    }

    public function test_store_accepts_active_registered_payment_method(): void
    {
        PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Voucher Empresa',
            'code' => 'voucher_empresa',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $response = $this->postJson('/api/v1/expenses', [
            'description' => 'Despesa com voucher',
            'amount' => 85.00,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'voucher_empresa',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.payment_method', 'voucher_empresa');
    }

    public function test_store_rejects_unknown_payment_method_code(): void
    {
        $response = $this->postJson('/api/v1/expenses', [
            'description' => 'Despesa invalida',
            'amount' => 40.00,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'metodo_inexistente',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }
}
