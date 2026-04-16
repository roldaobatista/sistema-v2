<?php

namespace Tests\Feature\Api\V1\Contracts;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractAddendumControllerTest extends TestCase
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
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_can_list_addendums_with_pagination(): void
    {
        ContractAddendum::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/contracts/addendums');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_addendum(): void
    {
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/contracts/addendums', [
            'contract_id' => $contract->id,
            'type' => 'value_change',
            'description' => 'Reajuste anual IPC',
            'new_value' => 3500.50,
            'effective_date' => '2026-04-01',
            'status' => 'pending',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('contract_addendums', [
            'contract_id' => $contract->id,
            'type' => 'value_change',
            'description' => 'Reajuste anual IPC',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_show_addendum(): void
    {
        $addendum = ContractAddendum::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/contracts/addendums/{$addendum->id}");

        $response->assertOk()
            ->assertJsonStructure(['id', 'contract_id', 'type', 'description', 'effective_date', 'contract']);
    }

    public function test_can_update_addendum(): void
    {
        $addendum = ContractAddendum::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/contracts/addendums/{$addendum->id}", [
            'description' => 'Atualizacao de escopo',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('contract_addendums', [
            'id' => $addendum->id,
            'description' => 'Atualizacao de escopo',
        ]);
    }

    public function test_can_delete_addendum(): void
    {
        $addendum = ContractAddendum::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/contracts/addendums/{$addendum->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('contract_addendums', ['id' => $addendum->id]);
    }

    public function test_store_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/contracts/addendums', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contract_id', 'type', 'description', 'effective_date']);
    }

    public function test_store_fails_with_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/contracts/addendums', [
            'contract_id' => 999999,
            'type' => '',
            'description' => '',
            'effective_date' => 'not-a-date',
            'new_value' => 'not-a-number',
        ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_access_addendum_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $addendum = ContractAddendum::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/contracts/addendums/{$addendum->id}");

        $response->assertNotFound();
    }

    public function test_only_lists_addendums_from_own_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        // Create addendums for other tenant
        ContractAddendum::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
        ]);

        // Create addendums for own tenant
        ContractAddendum::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/contracts/addendums');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_assigns_tenant_id_and_created_by_automatically(): void
    {
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/contracts/addendums', [
            'contract_id' => $contract->id,
            'type' => 'scope_change',
            'description' => 'Escopo alterado',
            'effective_date' => '2026-05-01',
        ]);

        $response->assertCreated();

        $addendum = ContractAddendum::latest('id')->first();
        $this->assertEquals($this->tenant->id, $addendum->tenant_id);
        $this->assertEquals($this->user->id, $addendum->created_by);
    }

    public function test_pagination_respects_per_page_parameter(): void
    {
        ContractAddendum::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/contracts/addendums?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_per_page_is_capped_at_100(): void
    {
        $response = $this->getJson('/api/v1/contracts/addendums?per_page=500');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_created_by_cannot_be_set_via_request(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/contracts/addendums', [
            'contract_id' => $contract->id,
            'type' => 'value_change',
            'description' => 'Test',
            'effective_date' => '2026-04-01',
            'created_by' => $otherUser->id, // Attempt to set created_by
        ]);

        $response->assertCreated();

        $addendum = ContractAddendum::latest('id')->first();
        // created_by should be the authenticated user, not the one sent in the request
        $this->assertEquals($this->user->id, $addendum->created_by);
    }
}
