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

class AccountPayableControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private AccountPayableCategory $category;

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

        $this->category = AccountPayableCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_payables(): void
    {
        AccountPayable::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        // Outro tenant
        $otherTenant = Tenant::factory()->create();
        $otherCategory = AccountPayableCategory::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        AccountPayable::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
            'category_id' => $otherCategory->id,
        ]);

        $response = $this->getJson('/api/v1/accounts-payable');

        $response->assertOk()->assertJsonStructure(['data']);

        foreach ($response->json('data') as $row) {
            $this->assertEquals($this->tenant->id, $row['tenant_id']);
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/accounts-payable', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'description', 'amount', 'due_date']);
    }

    public function test_store_rejects_zero_amount(): void
    {
        $response = $this->postJson('/api/v1/accounts-payable', [
            'category_id' => $this->category->id,
            'description' => 'Teste',
            'amount' => 0,
            'due_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_store_rejects_category_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignCategory = AccountPayableCategory::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/accounts-payable', [
            'category_id' => $foreignCategory->id,
            'description' => 'Cross tenant leak attempt',
            'amount' => 100,
            'due_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_store_creates_payable_with_tenant_and_created_by(): void
    {
        $response = $this->postJson('/api/v1/accounts-payable', [
            'category_id' => $this->category->id,
            'description' => 'Conta de luz',
            'amount' => 250.75,
            'due_date' => now()->addDays(15)->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('accounts_payable', [
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Conta de luz',
            'status' => FinancialStatus::PENDING->value,
        ]);
    }

    public function test_show_returns_404_for_cross_tenant_payable(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCategory = AccountPayableCategory::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $foreign = AccountPayable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
            'category_id' => $otherCategory->id,
        ]);

        $response = $this->getJson("/api/v1/accounts-payable/{$foreign->id}");

        $response->assertStatus(404);
    }

    public function test_update_rejects_edit_on_paid_payable(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'category_id' => $this->category->id,
            'status' => FinancialStatus::PAID->value,
            'amount' => 500,
            'amount_paid' => 500,
        ]);

        $response = $this->putJson("/api/v1/accounts-payable/{$ap->id}", [
            'description' => 'Tentativa de edição em título pago',
        ]);

        $response->assertStatus(422);
    }

    public function test_destroy_blocks_deletion_with_linked_payments(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'category_id' => $this->category->id,
            'amount' => 500,
            'amount_paid' => 100,
            'status' => FinancialStatus::PARTIAL->value,
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $ap->id,
            'received_by' => $this->user->id,
            'amount' => 100,
        ]);

        $response = $this->deleteJson("/api/v1/accounts-payable/{$ap->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('accounts_payable', ['id' => $ap->id]);
    }
}
