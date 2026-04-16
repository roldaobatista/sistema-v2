<?php

namespace Tests\Unit\Models;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ExpenseDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
    }

    public function test_expense_creation(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        $this->assertNotNull($exp);
    }

    public function test_expense_belongs_to_category(): void
    {
        $cat = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
        ]);
        $this->assertInstanceOf(ExpenseCategory::class, $exp->category);
    }

    public function test_expense_belongs_to_creator(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        $this->assertInstanceOf(User::class, $exp->creator);
    }

    public function test_expense_status_pending(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
        ]);
        $this->assertEquals(ExpenseStatus::PENDING, $exp->status);
    }

    public function test_expense_status_approved(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => 'approved',
        ]);
        $this->assertEquals(ExpenseStatus::APPROVED, $exp->status);
    }

    public function test_expense_status_rejected(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => 'rejected',
        ]);
        $this->assertEquals(ExpenseStatus::REJECTED, $exp->status);
    }

    public function test_expense_amount_precision(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '1234.56',
        ]);
        $this->assertEquals('1234.56', $exp->amount);
    }

    public function test_expense_date_cast(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_date' => '2026-03-16',
        ]);
        $exp->refresh();
        $this->assertInstanceOf(Carbon::class, $exp->expense_date);
    }

    public function test_expense_soft_deletes(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        $exp->delete();
        $this->assertNotNull(Expense::withTrashed()->find($exp->id));
    }

    // ── ExpenseCategory ──

    public function test_expense_category_has_many_expenses(): void
    {
        $cat = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Expense::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
        ]);
        $this->assertGreaterThanOrEqual(3, $cat->expenses()->count());
    }

    public function test_expense_category_has_name(): void
    {
        $cat = ExpenseCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertNotEmpty($cat->name);
    }

    public function test_expense_category_soft_deletes(): void
    {
        $cat = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $cat->delete();
        $this->assertNotNull(ExpenseCategory::withTrashed()->find($cat->id));
    }

    // ── Expense Approval Flow ──

    public function test_expense_can_be_approved(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
        ]);
        $exp->update(['status' => 'approved']);
        $this->assertEquals(ExpenseStatus::APPROVED, $exp->fresh()->status);
    }

    // ── Scopes ──

    public function test_expense_scope_by_status(): void
    {
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
        ]);
        $results = Expense::where('status', 'pending')->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_expense_scope_by_date_range(): void
    {
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_date' => now(),
        ]);
        $results = Expense::whereBetween('expense_date', [now()->subDay(), now()->addDay()])->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_expense_scope_by_category(): void
    {
        $cat = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
        ]);
        $results = Expense::where('expense_category_id', $cat->id)->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_expense_total_by_category(): void
    {
        $cat = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
            'amount' => '500.00',
        ]);
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
            'amount' => '300.00',
        ]);
        $total = Expense::where('expense_category_id', $cat->id)->sum('amount');
        $this->assertEquals('800.00', $total);
    }
}
