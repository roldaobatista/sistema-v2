<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseStatusHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicianExpenseTest extends TestCase
{
    private Tenant $tenant;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->technician, ['*']);
        Gate::before(fn () => true);
    }

    public function test_index_returns_only_authenticated_user_expenses(): void
    {
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->technician->id,
            'description' => 'Minha despesa',
        ]);

        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => User::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'description' => 'Despesa de outro usuário',
        ]);

        $response = $this->getJson('/api/v1/technician-cash/my-expenses');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Minha despesa');
    }

    public function test_store_creates_pending_expense_with_history_for_authorized_work_order(): void
    {
        $category = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
        ]);

        $response = $this->postJson('/api/v1/technician-cash/my-expenses', [
            'work_order_id' => $workOrder->id,
            'expense_category_id' => $category->id,
            'description' => 'Combustível',
            'amount' => 89.90,
            'expense_date' => now()->toDateString(),
            'affects_technician_cash' => true,
            'affects_net_value' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', ExpenseStatus::PENDING->value)
            ->assertJsonPath('data.work_order.id', $workOrder->id);

        $expenseId = $response->json('data.id');

        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->technician->id,
            'status' => ExpenseStatus::PENDING->value,
        ]);

        $this->assertDatabaseHas('expense_status_history', [
            'expense_id' => $expenseId,
            'to_status' => ExpenseStatus::PENDING->value,
        ]);
    }

    public function test_update_rejected_expense_reopens_it_as_pending(): void
    {
        $expense = Expense::factory()->rejected()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->technician->id,
            'description' => 'Rejeitada',
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->putJson("/api/v1/technician-cash/my-expenses/{$expense->id}", [
            'description' => 'Reenviada',
            'amount' => 120.50,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', ExpenseStatus::PENDING->value)
            ->assertJsonPath('data.rejection_reason', null);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'description' => 'Reenviada',
            'status' => ExpenseStatus::PENDING->value,
            'rejection_reason' => null,
        ]);

        $this->assertTrue(
            ExpenseStatusHistory::query()
                ->where('expense_id', $expense->id)
                ->where('from_status', ExpenseStatus::REJECTED->value)
                ->where('to_status', ExpenseStatus::PENDING->value)
                ->exists()
        );
    }

    public function test_destroy_returns_not_found_for_expense_owned_by_another_user(): void
    {
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => User::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'expense_date' => now()->toDateString(),
        ]);

        $this->deleteJson("/api/v1/technician-cash/my-expenses/{$expense->id}")
            ->assertStatus(404);
    }

    public function test_update_replaces_receipt_without_leaving_orphans(): void
    {
        Storage::fake('public');

        $oldReceipt = UploadedFile::fake()->image('old-receipt.jpg');
        $oldDiskPath = $oldReceipt->store("tenants/{$this->tenant->id}/receipts", 'public');

        $expense = Expense::factory()->rejected()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->technician->id,
            'expense_date' => now()->toDateString(),
            'receipt_path' => "/storage/{$oldDiskPath}",
        ]);

        $newReceipt = UploadedFile::fake()->image('new-receipt.jpg');

        $response = $this->putJson("/api/v1/technician-cash/my-expenses/{$expense->id}", [
            'description' => 'Despesa com novo comprovante',
            'amount' => 150.30,
            'expense_date' => now()->toDateString(),
            'receipt' => $newReceipt,
        ]);

        $response->assertOk();

        $expense->refresh();
        $newDiskPath = ltrim(str_replace('/storage/', '', (string) $expense->receipt_path), '/');

        Storage::disk('public')->assertExists($newDiskPath);
        Storage::disk('public')->assertMissing($oldDiskPath);
    }
}
