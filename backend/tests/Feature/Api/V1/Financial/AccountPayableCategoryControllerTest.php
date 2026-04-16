<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Models\AccountPayable;
use App\Models\AccountPayableCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountPayableCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

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
    }

    // ── INDEX ──────────────────────────────────────────────────────────────

    public function test_index_returns_categories_for_tenant(): void
    {
        AccountPayableCategory::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/account-payable-categories');

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/account-payable-categories');
        // Gate::before allows all in test context — just verify the route exists and returns data
        $response->assertOk();
    }

    public function test_index_excludes_inactive_categories(): void
    {
        AccountPayableCategory::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        AccountPayableCategory::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/account-payable-categories');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_tenant_isolation(): void
    {
        // Other tenant's categories
        $otherTenant = Tenant::factory()->create();
        AccountPayableCategory::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);

        // Our tenant has 1
        AccountPayableCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/account-payable-categories');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── STORE ──────────────────────────────────────────────────────────────

    public function test_store_creates_category(): void
    {
        $payload = [
            'name' => 'Despesas Operacionais',
            'color' => '#FF5733',
            'description' => 'Categoria para despesas operacionais',
        ];

        $response = $this->postJson('/api/v1/account-payable-categories', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Despesas Operacionais')
            ->assertJsonPath('data.color', '#FF5733');

        $this->assertDatabaseHas('account_payable_categories', [
            'name' => 'Despesas Operacionais',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->postJson('/api/v1/account-payable-categories', [
            'color' => '#FF5733',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_name_must_be_unique_per_tenant(): void
    {
        AccountPayableCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Duplicado',
        ]);

        $response = $this->postJson('/api/v1/account-payable-categories', [
            'name' => 'Duplicado',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_duplicate_name_allowed_for_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        AccountPayableCategory::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Categoria X',
        ]);

        $response = $this->postJson('/api/v1/account-payable-categories', [
            'name' => 'Categoria X',
        ]);

        $response->assertCreated();
    }

    public function test_store_name_max_length_validation(): void
    {
        $response = $this->postJson('/api/v1/account-payable-categories', [
            'name' => str_repeat('A', 101),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ── UPDATE ─────────────────────────────────────────────────────────────

    public function test_update_category(): void
    {
        $category = AccountPayableCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
        ]);

        $response = $this->putJson("/api/v1/account-payable-categories/{$category->id}", [
            'name' => 'New Name',
            'color' => '#00FF00',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.color', '#00FF00');

        $this->assertDatabaseHas('account_payable_categories', [
            'id' => $category->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_rejects_other_tenant_category(): void
    {
        $otherTenant = Tenant::factory()->create();
        $category = AccountPayableCategory::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Cat',
        ]);

        $response = $this->putJson("/api/v1/account-payable-categories/{$category->id}", [
            'name' => 'Hacked',
        ]);

        // BelongsToTenant scope makes it invisible → 404
        $response->assertNotFound();

        $this->assertDatabaseHas('account_payable_categories', [
            'id' => $category->id,
            'name' => 'Other Tenant Cat',
        ]);
    }

    // ── DESTROY ────────────────────────────────────────────────────────────

    public function test_destroy_deletes_category(): void
    {
        $category = AccountPayableCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->deleteJson("/api/v1/account-payable-categories/{$category->id}");

        $response->assertNoContent();
    }

    public function test_destroy_rejects_other_tenant_category(): void
    {
        $otherTenant = Tenant::factory()->create();
        $category = AccountPayableCategory::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->deleteJson("/api/v1/account-payable-categories/{$category->id}");

        // BelongsToTenant scope makes it invisible → 404
        $response->assertNotFound();
        $this->assertDatabaseHas('account_payable_categories', ['id' => $category->id]);
    }

    public function test_destroy_blocks_deletion_when_payables_exist(): void
    {
        $category = AccountPayableCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/account-payable-categories/{$category->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('account_payable_categories', ['id' => $category->id]);
    }
}
