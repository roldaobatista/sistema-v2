<?php

namespace Tests\Unit\Services;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private ExpenseService $service;

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

        $this->service = new ExpenseService;
    }

    public function test_validate_limits_returns_null_when_no_category(): void
    {
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => null,
        ]);

        $this->assertNull($this->service->validateLimits($expense));
    }

    public function test_validate_limits_returns_null_when_within_budget(): void
    {
        $cat = ExpenseCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'budget_limit' => 10000,
        ]);

        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
            'amount' => '100.00',
            'expense_date' => now(),
        ]);

        $result = $this->service->validateLimits($expense);
        $this->assertNull($result);
    }

    public function test_validate_limits_returns_warning_when_exceeded(): void
    {
        $cat = ExpenseCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'budget_limit' => 500,
        ]);

        // Create expenses exceeding the limit
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
            'amount' => '600.00',
            'expense_date' => now(),
            'status' => ExpenseStatus::APPROVED,
        ]);

        $newExpense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
            'amount' => '100.00',
            'expense_date' => now(),
            'status' => ExpenseStatus::APPROVED,
        ]);

        $result = $this->service->validateLimits($newExpense);
        $this->assertNotNull($result);
        $this->assertStringContainsString('ultrapassado', $result);
    }

    public function test_validate_limits_warns_at_80_percent(): void
    {
        $cat = ExpenseCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'budget_limit' => 1000,
        ]);

        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
            'amount' => '850.00',
            'expense_date' => now(),
            'status' => ExpenseStatus::APPROVED,
        ]);

        $newExpense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
            'amount' => '10.00',
            'expense_date' => now(),
            'status' => ExpenseStatus::APPROVED,
        ]);

        $result = $this->service->validateLimits($newExpense);
        $this->assertNotNull($result);
        $this->assertStringContainsString('Atenção', $result);
    }

    public function test_validate_limits_returns_null_no_budget_set(): void
    {
        $cat = ExpenseCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'budget_limit' => null,
        ]);

        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
            'amount' => '9999.99',
            'expense_date' => now(),
        ]);

        $this->assertNull($this->service->validateLimits($expense));
    }

    public function test_validate_limits_excludes_rejected_expenses(): void
    {
        $cat = ExpenseCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'budget_limit' => 500,
        ]);

        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
            'amount' => '600.00',
            'expense_date' => now(),
            'status' => ExpenseStatus::REJECTED,
        ]);

        $newExpense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
            'amount' => '100.00',
            'expense_date' => now(),
        ]);

        $result = $this->service->validateLimits($newExpense);
        $this->assertNull($result);
    }
}
