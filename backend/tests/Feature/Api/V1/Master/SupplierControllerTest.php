<?php

namespace Tests\Feature\Api\V1\Master;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

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
        $this->otherTenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ═══════════════════════════════════════════════════════════
    // Index
    // ═══════════════════════════════════════════════════════════

    public function test_index_returns_paginated_suppliers(): void
    {
        Supplier::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/suppliers');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ]);
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    public function test_index_filters_by_search_name(): void
    {
        Supplier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Corp']);
        Supplier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beta Ltd']);

        $response = $this->getJson('/api/v1/suppliers?search=Acme');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Acme Corp'));
        $this->assertFalse($names->contains('Beta Ltd'));
    }

    public function test_index_filters_by_search_document(): void
    {
        Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'DocSupplier',
            'document' => '12.345.678/0001-90',
        ]);

        $response = $this->getJson('/api/v1/suppliers?search=12.345');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('DocSupplier'));
    }

    public function test_index_filters_by_type(): void
    {
        Supplier::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'PF', 'name' => 'Pessoa Fisica']);
        Supplier::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'PJ', 'name' => 'Pessoa Juridica']);

        $response = $this->getJson('/api/v1/suppliers?type=PF');

        $response->assertOk();
        foreach ($response->json('data') as $supplier) {
            $this->assertEquals('PF', $supplier['type']);
        }
    }

    public function test_index_filters_by_is_active(): void
    {
        Supplier::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true, 'name' => 'Active']);
        Supplier::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false, 'name' => 'Inactive']);

        $response = $this->getJson('/api/v1/suppliers?is_active=0');

        $response->assertOk();
        foreach ($response->json('data') as $supplier) {
            $this->assertFalse($supplier['is_active']);
        }
    }

    public function test_index_does_not_leak_other_tenant_suppliers(): void
    {
        Supplier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'My Supplier']);
        Supplier::factory()->create(['tenant_id' => $this->otherTenant->id, 'name' => 'Other Supplier']);

        $response = $this->getJson('/api/v1/suppliers');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('My Supplier'));
        $this->assertFalse($names->contains('Other Supplier'));
    }

    // ═══════════════════════════════════════════════════════════
    // Store
    // ═══════════════════════════════════════════════════════════

    public function test_store_creates_supplier(): void
    {
        $payload = [
            'type' => 'PJ',
            'name' => 'Fornecedor Novo',
            'document' => '99.999.999/0001-99',
            'email' => 'contato@fornecedor.com',
            'phone' => '11999999999',
            'address_city' => 'Sao Paulo',
            'address_state' => 'SP',
        ];

        $response = $this->postJson('/api/v1/suppliers', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Fornecedor Novo')
            ->assertJsonPath('data.type', 'PJ')
            ->assertJsonPath('data.email', 'contato@fornecedor.com');

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Fornecedor Novo',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/suppliers', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'name']);
    }

    public function test_store_validates_type_must_be_pf_or_pj(): void
    {
        $response = $this->postJson('/api/v1/suppliers', [
            'type' => 'XX',
            'name' => 'Test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_validates_unique_document_per_tenant(): void
    {
        Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document' => '11.111.111/0001-11',
        ]);

        $response = $this->postJson('/api/v1/suppliers', [
            'type' => 'PJ',
            'name' => 'Duplicado',
            'document' => '11.111.111/0001-11',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document']);
    }

    public function test_store_allows_same_document_in_different_tenant(): void
    {
        Supplier::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'document' => '22.222.222/0001-22',
        ]);

        $response = $this->postJson('/api/v1/suppliers', [
            'type' => 'PJ',
            'name' => 'Not Duplicado',
            'document' => '22.222.222/0001-22',
        ]);

        $response->assertStatus(201);
    }

    // ═══════════════════════════════════════════════════════════
    // Show
    // ═══════════════════════════════════════════════════════════

    public function test_show_returns_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Show Test',
        ]);

        $response = $this->getJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Show Test')
            ->assertJsonPath('data.id', $supplier->id);
    }

    // ═══════════════════════════════════════════════════════════
    // Update
    // ═══════════════════════════════════════════════════════════

    public function test_update_modifies_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
        ]);

        $response = $this->putJson("/api/v1/suppliers/{$supplier->id}", [
            'name' => 'Updated Name',
            'email' => 'new@email.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', 'new@email.com');
    }

    // ═══════════════════════════════════════════════════════════
    // Destroy
    // ═══════════════════════════════════════════════════════════

    public function test_destroy_deletes_supplier(): void
    {
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->deleteJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_destroy_blocks_when_has_accounts_payable(): void
    {
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountPayable::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'description' => 'Compra de material',
            'amount' => 1000,
            'amount_paid' => 0,
            'due_date' => now()->addDays(30),
            'status' => 'pending',
        ]);

        $response = $this->deleteJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(409)
            ->assertJsonPath('dependencies.accounts_payable', 1);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════
    // Resource structure
    // ═══════════════════════════════════════════════════════════

    public function test_store_response_has_correct_resource_structure(): void
    {
        $response = $this->postJson('/api/v1/suppliers', [
            'type' => 'PF',
            'name' => 'Resource Check',
            'document' => '000.000.000-00',
            'phone' => '1199999',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'tenant_id',
                    'type',
                    'name',
                    'document',
                    'trade_name',
                    'email',
                    'phone',
                    'phone2',
                    'address_zip',
                    'address_street',
                    'address_number',
                    'address_complement',
                    'address_neighborhood',
                    'address_city',
                    'address_state',
                    'notes',
                    'is_active',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }
}
