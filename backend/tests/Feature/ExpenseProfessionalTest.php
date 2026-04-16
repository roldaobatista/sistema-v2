<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\TechnicianCashFund;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseProfessionalTest extends TestCase
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
     * Helper to create an Expense with status (forceFill).
     */
    private function createExpense(array $attributes = [], string $status = ExpenseStatus::PENDING->value): Expense
    {
        $expense = new Expense(array_diff_key($attributes, ['status' => 1]));
        $expense->forceFill(['status' => $status]);
        $expense->save();

        return $expense;
    }

    public function test_technician_cash_debit_created_on_approval(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Taxi',
            'amount' => 50.00,
            'expense_date' => now()->toDateString(),
            'affects_technician_cash' => true,
        ]);

        Sanctum::actingAs($approver, ['*']);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::APPROVED->value,
        ])->assertOk();

        $fund = TechnicianCashFund::where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($fund);
        $this->assertEquals(-50.0, (float) $fund->balance);
    }

    public function test_technician_cash_not_affected_if_flag_false(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Material Escrita',
            'amount' => 20.00,
            'expense_date' => now()->toDateString(),
            'affects_technician_cash' => false,
        ]);

        Sanctum::actingAs($approver, ['*']);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::APPROVED->value,
        ])->assertOk();

        $fund = TechnicianCashFund::where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNull($fund);
    }

    public function test_technician_cash_credit_created_on_reimbursement(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Alimentacao',
            'amount' => 30.00,
            'expense_date' => now()->toDateString(),
            'affects_technician_cash' => true,
        ]);

        Sanctum::actingAs($approver, ['*']);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::APPROVED->value,
        ])->assertOk();

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::REIMBURSED->value,
        ])->assertOk();

        $fund = TechnicianCashFund::where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertEquals(0.0, (float) $fund->balance);
    }

    public function test_technician_cash_not_affected_on_reimbursement_if_flag_false(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Material',
            'amount' => 45.00,
            'expense_date' => now()->toDateString(),
            'affects_technician_cash' => false,
        ]);

        Sanctum::actingAs($approver, ['*']);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::APPROVED->value,
        ])->assertOk();

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::REIMBURSED->value,
        ])->assertOk();

        $fund = TechnicianCashFund::where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNull($fund);
    }

    public function test_cannot_approve_own_expense_if_permission_check_enforced(): void
    {
        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Self',
            'amount' => 10,
            'expense_date' => now()->toDateString(),
        ]);

        // User without SUPER_ADMIN role cannot approve their own expense
        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::APPROVED->value,
        ])->assertStatus(403);

        $this->assertNull($expense->fresh()->approved_by);
    }

    public function test_budget_warning_returned_on_store(): void
    {
        $category = ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Limitada',
            'budget_limit' => 100.00,
            'color' => '#123456',
            'active' => true,
        ]);

        // Spend 90
        $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'expense_category_id' => $category->id,
            'created_by' => $this->user->id,
            'description' => 'Gasto 1',
            'amount' => 90.00,
            'expense_date' => now()->toDateString(),
        ], ExpenseStatus::APPROVED->value);

        // New expense of 20 exceeds 100
        $response = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $category->id,
            'description' => 'Gasto 2',
            'amount' => 20.00,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertStringContainsString('Orcamento da categoria \'Limitada\' ultrapassado', $response->json('_budget_warning'));
    }

    public function test_budget_warning_returned_on_update(): void
    {
        $category = ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'LimitadaUpdate',
            'budget_limit' => 50.00,
            'color' => '#654321',
            'active' => true,
        ]);

        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'expense_category_id' => $category->id,
            'created_by' => $this->user->id,
            'description' => 'Gasto Inicial',
            'amount' => 40.00,
            'expense_date' => now()->toDateString(),
        ]);

        $this->putJson("/api/v1/expenses/{$expense->id}", [
            'amount' => 60.00,
        ])->assertOk()
            ->assertJsonFragment(['_budget_warning' => "Orcamento da categoria 'LimitadaUpdate' ultrapassado: R$ 60.00 de R$ 50.00"]);
    }

    public function test_duplicate_expense_creates_copy(): void
    {
        $original = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Original',
            'amount' => 123.45,
            'expense_date' => now()->subDay()->toDateString(),
            'notes' => 'Notas originais',
            'affects_technician_cash' => true,
        ], ExpenseStatus::APPROVED->value);

        $response = $this->postJson("/api/v1/expenses/{$original->id}/duplicate");

        $response->assertStatus(201);

        $newId = $response->json('data.id');
        $this->assertNotEquals($original->id, $newId);

        $copy = Expense::find($newId);
        $this->assertEquals('Original (Cópia)', $copy->description);
        $this->assertEquals(123.45, $copy->amount);
        $this->assertEquals(now()->toDateString(), $copy->expense_date->toDateString());
        $this->assertEquals(ExpenseStatus::PENDING->value, $copy->status->value);
        $this->assertNull($copy->approved_by);
        $this->assertEquals('Notas originais', $copy->notes);
        $this->assertTrue((bool) $copy->affects_technician_cash);
        $this->assertEquals($this->user->id, $copy->created_by);
    }

    public function test_duplicate_blocks_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignExpense = $this->createExpense([
            'tenant_id' => $otherTenant->id,
            'created_by' => User::factory()->create(['tenant_id' => $otherTenant->id, 'current_tenant_id' => $otherTenant->id])->id,
            'description' => 'Outro',
            'amount' => 10,
            'expense_date' => now()->toDateString(),
        ]);

        $this->postJson("/api/v1/expenses/{$foreignExpense->id}/duplicate")
            ->assertStatus(404);
    }

    public function test_update_category_active_status(): void
    {
        $category = ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ativa',
            'active' => true,
        ]);

        $this->putJson("/api/v1/expense-categories/{$category->id}", [
            'active' => false,
        ])->assertOk();

        $this->assertFalse((bool) $category->fresh()->active);
    }

    public function test_store_expense_validates_category_active(): void
    {
        $inactiveCategory = ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inativa',
            'active' => false,
        ]);

        $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $inactiveCategory->id,
            'description' => 'Teste em inativa',
            'amount' => 10,
            'expense_date' => now()->toDateString(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['expense_category_id']);
    }

    public function test_reviewed_status_transition(): void
    {
        $reviewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $expense = $this->createExpense([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 100,
            'expense_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($reviewer, ['*']);

        $this->putJson("/api/v1/expenses/{$expense->id}/status", [
            'status' => ExpenseStatus::REVIEWED->value,
        ])->assertOk();

        $expense->refresh();
        $this->assertEquals(ExpenseStatus::REVIEWED, $expense->status);
        $this->assertEquals($reviewer->id, $expense->reviewed_by);
        $this->assertNotNull($expense->reviewed_at);
    }
}
