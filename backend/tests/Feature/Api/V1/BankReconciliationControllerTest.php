<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BankReconciliationControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createStatement(?int $tenantId = null, ?int $bankAccountId = null): BankStatement
    {
        return BankStatement::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'bank_account_id' => $bankAccountId ?? $this->bankAccount->id,
            'filename' => 'extrato-'.uniqid().'.ofx',
            'format' => 'ofx',
            'imported_at' => now(),
            'total_entries' => 0,
            'matched_entries' => 0,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_summary_returns_200(): void
    {
        $response = $this->getJson('/api/v1/bank-reconciliation/summary');

        $response->assertOk();
    }

    public function test_statements_returns_only_current_tenant_statements(): void
    {
        $this->createStatement();

        // Statement em outro tenant
        $otherTenant = Tenant::factory()->create();
        $otherBankAccount = BankAccount::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        BankStatement::create([
            'tenant_id' => $otherTenant->id,
            'bank_account_id' => $otherBankAccount->id,
            'filename' => 'LEAK.ofx',
            'format' => 'ofx',
            'imported_at' => now(),
            'total_entries' => 0,
            'matched_entries' => 0,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/bank-reconciliation/statements');

        $response->assertOk();

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK.ofx', $payload, 'Statement de outro tenant vazou');
    }

    public function test_entries_returns_404_for_cross_tenant_statement(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherBankAccount = BankAccount::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignStatement = $this->createStatement($otherTenant->id, $otherBankAccount->id);

        $response = $this->getJson("/api/v1/bank-reconciliation/statements/{$foreignStatement->id}/entries");

        $response->assertStatus(404);
    }

    public function test_unmatch_rejects_pending_entry(): void
    {
        $statement = $this->createStatement();

        $entry = BankStatementEntry::create([
            'tenant_id' => $this->tenant->id,
            'bank_statement_id' => $statement->id,
            'date' => now()->toDateString(),
            'description' => 'Teste',
            'amount' => 100,
            'type' => 'credit',
            'status' => BankStatementEntry::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/v1/bank-reconciliation/entries/{$entry->id}/unmatch");

        $response->assertStatus(422);
    }

    public function test_ignore_entry_marks_as_ignored(): void
    {
        $statement = $this->createStatement();

        $entry = BankStatementEntry::create([
            'tenant_id' => $this->tenant->id,
            'bank_statement_id' => $statement->id,
            'date' => now()->toDateString(),
            'description' => 'A ignorar',
            'amount' => 50,
            'type' => 'debit',
            'status' => BankStatementEntry::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/v1/bank-reconciliation/entries/{$entry->id}/ignore");

        $response->assertOk();
        $this->assertDatabaseHas('bank_statement_entries', [
            'id' => $entry->id,
            'status' => BankStatementEntry::STATUS_IGNORED,
        ]);
    }

    public function test_ignore_entry_fails_for_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherBankAccount = BankAccount::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignStatement = $this->createStatement($otherTenant->id, $otherBankAccount->id);

        $foreignEntry = BankStatementEntry::create([
            'tenant_id' => $otherTenant->id,
            'bank_statement_id' => $foreignStatement->id,
            'date' => now()->toDateString(),
            'description' => 'foreign',
            'amount' => 10,
            'type' => 'debit',
            'status' => BankStatementEntry::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/v1/bank-reconciliation/entries/{$foreignEntry->id}/ignore");

        $response->assertStatus(404);
        // Entry foreign não pode ter sido alterado
        $this->assertDatabaseHas('bank_statement_entries', [
            'id' => $foreignEntry->id,
            'status' => BankStatementEntry::STATUS_PENDING,
        ]);
    }

    public function test_match_entry_validates_required_fields(): void
    {
        $statement = $this->createStatement();
        $entry = BankStatementEntry::create([
            'tenant_id' => $this->tenant->id,
            'bank_statement_id' => $statement->id,
            'date' => now()->toDateString(),
            'description' => 'Match test',
            'amount' => 100,
            'type' => 'credit',
            'status' => BankStatementEntry::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/v1/bank-reconciliation/entries/{$entry->id}/match", []);

        $response->assertStatus(422);
    }
}
