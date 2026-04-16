<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Lookups\CustomerCompanySize;
use App\Models\Lookups\CustomerRating;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cadastros Deep Audit Tests — validates Lookup CRUD, Customer CRUD (with contacts sync,
 * multi-tenant isolation, dependency checks), Product CRUD (tenant-scoped codes, categories),
 * and Supplier CRUD (tenant scoping, dependency checks).
 */
class CadastrosDeepAuditTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenantA = Tenant::factory()->create(['name' => 'CadTenantA', 'status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['name' => 'CadTenantB', 'status' => 'active']);

        $this->adminA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'email' => 'admin@cad.test',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->adminA->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        $this->adminB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'email' => 'admin-b@cad.test',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->adminB->tenants()->attach($this->tenantB->id, ['is_default' => true]);

        $this->withoutMiddleware(CheckPermission::class);
        app()->instance('current_tenant_id', $this->tenantA->id);
    }

    // ══════════════════════════════════════════════
    // ── AUTH — 401 UNAUTHENTICATED
    // ══════════════════════════════════════════════

    public function test_customers_requires_authentication(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }

    public function test_products_requires_authentication(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_suppliers_requires_authentication(): void
    {
        $this->getJson('/api/v1/suppliers')->assertUnauthorized();
    }

    // ══════════════════════════════════════════════
    // ── LOOKUP CRUD
    // ══════════════════════════════════════════════

    public function test_list_lookup_types(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/lookups/types');
        $response->assertOk();

        $types = $response->json();
        $this->assertContains('equipment-categories', $types);
        $this->assertContains('customer-segments', $types);
        $this->assertContains('customer-company-sizes', $types);
        $this->assertContains('customer-ratings', $types);
    }

    public function test_create_lookup_item(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/lookups/equipment-categories', [
            'name' => 'Balança Rodoviária',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Balança Rodoviária');
    }

    public function test_create_duplicate_lookup_name_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $this->postJson('/api/v1/lookups/equipment-categories', ['name' => 'Duplicada'])->assertCreated();

        $response = $this->postJson('/api/v1/lookups/equipment-categories', ['name' => 'Duplicada']);
        $response->assertStatus(422);
    }

    public function test_update_lookup_item(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $create = $this->postJson('/api/v1/lookups/equipment-categories', ['name' => 'Original']);
        $id = $create->json('data.id') ?? $create->json('data.id');

        $response = $this->putJson("/api/v1/lookups/equipment-categories/{$id}", [
            'name' => 'Atualizada',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Atualizada');
    }

    public function test_delete_lookup_item(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $create = $this->postJson('/api/v1/lookups/customer-segments', ['name' => 'Para Excluir']);
        $id = $create->json('data.id') ?? $create->json('data.id');

        $response = $this->deleteJson("/api/v1/lookups/customer-segments/{$id}");
        $response->assertOk();
    }

    public function test_invalid_lookup_type_returns_404(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/lookups/invalid-type');
        $response->assertNotFound();
    }

    // ══════════════════════════════════════════════
    // ── CUSTOMER CRUD
    // ══════════════════════════════════════════════

    public function test_create_customer(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Cliente Teste',
            'type' => 'PJ',
            'email' => 'cliente@test.com',
            'phone' => '(65) 99999-1234',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Cliente Teste');

        $customer = Customer::where('email', 'cliente@test.com')->first();
        $this->assertNotNull($customer);
        $this->assertEquals($this->tenantA->id, $customer->tenant_id);
    }

    public function test_create_customer_with_contacts(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Com Contatos',
            'type' => 'PF',
            'contacts' => [
                ['name' => 'João', 'email' => 'joao@test.com', 'phone' => '999991234'],
                ['name' => 'Maria', 'email' => 'maria@test.com', 'phone' => '999995678'],
            ],
        ]);

        $response->assertCreated();
        $this->assertCount(2, $response->json('data.contacts'));
    }

    public function test_list_customers_with_search(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Empresa Alpha',
            'email' => 'alpha@test.com',
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Empresa Beta',
            'email' => 'beta@test.com',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/customers?search=Alpha');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Empresa Alpha'));
        $this->assertFalse($names->contains('Empresa Beta'));
    }

    public function test_customer_options_returns_enums(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/customers/options');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['sources', 'segments', 'company_sizes', 'ratings', 'contract_types']]);
    }

    public function test_customer_options_prefers_lookup_values_for_company_size_and_rating(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        CustomerCompanySize::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        CustomerRating::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'AA - Estrategico',
            'slug' => 'AA',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $response = $this->getJson('/api/v1/customers/options');

        $response->assertOk();
        $this->assertArrayHasKey('enterprise', $response->json('data.company_sizes'));
        $this->assertArrayHasKey('AA', $response->json('data.ratings'));
    }

    public function test_update_customer(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Antigo Nome',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Novo Nome',
            'type' => 'PJ',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Novo Nome');
        $this->assertEquals('Novo Nome', $customer->fresh()->name);
    }

    public function test_delete_customer_without_dependencies_succeeds(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id, 'deleted_at' => null]);
    }

    public function test_delete_customer_with_active_work_orders_blocked(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    // ══════════════════════════════════════════════
    // ── CUSTOMER MULTI-TENANT ISOLATION
    // ══════════════════════════════════════════════

    public function test_customer_list_only_shows_own_tenant(): void
    {
        // tenantA customer (should appear)
        Customer::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Customer A',
        ]);

        // tenantB customer (must NOT appear)
        app()->instance('current_tenant_id', $this->tenantB->id);
        Customer::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Customer B Secret',
        ]);
        app()->instance('current_tenant_id', $this->tenantA->id);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/customers');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Customer A'));
        $this->assertFalse($names->contains('Customer B Secret'));
    }

    public function test_customer_from_other_tenant_returns_404(): void
    {
        app()->instance('current_tenant_id', $this->tenantB->id);
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);
        app()->instance('current_tenant_id', $this->tenantA->id);

        Sanctum::actingAs($this->adminA, ['*']);
        // Show uses route model binding with BelongsToTenant scope — will return 404
        $response = $this->getJson("/api/v1/customers/{$customerB->id}");

        $response->assertNotFound();
    }

    // ══════════════════════════════════════════════
    // ── PRODUCT CRUD
    // ══════════════════════════════════════════════

    public function test_create_product(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Peça X',
            'code' => 'PX-001',
            'cost_price' => 10.50,
            'sell_price' => 25.00,
            'stock_qty' => 100,
            'stock_min' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Peça X')
            ->assertJsonPath('data.code', 'PX-001');
    }

    public function test_create_product_duplicate_code_fails(): void
    {
        Product::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'DUPL-001',
            'name' => 'Existing',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Duplicado',
            'code' => 'DUPL-001',
            'cost_price' => 0,
            'sell_price' => 0,
            'stock_qty' => 0,
            'stock_min' => 0,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_same_product_code_allowed_in_different_tenants(): void
    {
        // Another tenant already has this code
        app()->instance('current_tenant_id', $this->tenantB->id);
        Product::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'SHARED-CODE',
            'name' => 'B Product',
        ]);
        app()->instance('current_tenant_id', $this->tenantA->id);

        Sanctum::actingAs($this->adminA, ['*']);
        // tenantA can create the same code
        $response = $this->postJson('/api/v1/products', [
            'name' => 'A Product',
            'code' => 'SHARED-CODE',
            'cost_price' => 0,
            'sell_price' => 0,
            'stock_qty' => 0,
            'stock_min' => 0,
        ]);

        $response->assertCreated();
    }

    public function test_list_products_with_low_stock_filter(): void
    {
        Product::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Low Stock',
            'stock_qty' => 2,
            'stock_min' => 10,
        ]);
        Product::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Good Stock',
            'stock_qty' => 100,
            'stock_min' => 10,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/products?low_stock=1');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Low Stock'));
        $this->assertFalse($names->contains('Good Stock'));
    }

    // ══════════════════════════════════════════════
    // ── PRODUCT CATEGORIES
    // ══════════════════════════════════════════════

    public function test_create_product_category(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->postJson('/api/v1/product-categories', ['name' => 'Peças']);
        $response->assertCreated()->assertJsonPath('data.name', 'Peças');
    }

    public function test_delete_category_with_products_returns_409(): void
    {
        $cat = ProductCategory::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cat Vinculada',
        ]);
        Product::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'category_id' => $cat->id,
            'name' => 'Produto Vinculado',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson("/api/v1/product-categories/{$cat->id}");
        $response->assertStatus(409);
    }

    // ══════════════════════════════════════════════
    // ── SUPPLIER CRUD
    // ══════════════════════════════════════════════

    public function test_create_supplier(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/suppliers', [
            'type' => 'PJ',
            'name' => 'Fornecedor ABC',
            'document' => '12.345.678/0001-99',
            'email' => 'contato@fornecedor.com',
            'phone' => '(65) 3321-0000',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Fornecedor ABC');

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Fornecedor ABC',
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    public function test_supplier_document_unique_per_tenant(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        // First supplier
        $this->postJson('/api/v1/suppliers', [
            'type' => 'PJ',
            'name' => 'Primeiro',
            'document' => '11.111.111/0001-11',
        ])->assertCreated();

        // Same document in same tenant
        $response = $this->postJson('/api/v1/suppliers', [
            'type' => 'PJ',
            'name' => 'Duplicado',
            'document' => '11.111.111/0001-11',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('document');
    }

    public function test_supplier_list_scoped_to_tenant(): void
    {
        // Create suppliers directly (avoid auth complexity in setup)
        app()->instance('current_tenant_id', $this->tenantA->id);
        Supplier::create(['tenant_id' => $this->tenantA->id, 'type' => 'PF', 'name' => 'Fornecedor A']);

        app()->instance('current_tenant_id', $this->tenantB->id);
        Supplier::create(['tenant_id' => $this->tenantB->id, 'type' => 'PF', 'name' => 'Fornecedor B']);

        // tenantA sees only its own
        Sanctum::actingAs($this->adminA, ['*']);
        app()->instance('current_tenant_id', $this->tenantA->id);
        $response = $this->getJson('/api/v1/suppliers');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Fornecedor A'));
        $this->assertFalse($names->contains('Fornecedor B'));
    }

    public function test_update_supplier(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $create = $this->postJson('/api/v1/suppliers', [
            'type' => 'PJ',
            'name' => 'Nome Original',
        ])->assertCreated();

        $id = $create->json('data.id') ?? $create->json('data.id');

        $response = $this->putJson("/api/v1/suppliers/{$id}", [
            'name' => 'Nome Atualizado',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Nome Atualizado');
    }

    public function test_delete_supplier_without_dependencies(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $create = $this->postJson('/api/v1/suppliers', [
            'type' => 'PJ',
            'name' => 'Para Excluir',
        ])->assertCreated();

        $id = $create->json('data.id') ?? $create->json('data.id');

        $response = $this->deleteJson("/api/v1/suppliers/{$id}");
        $response->assertNoContent();
    }

    public function test_create_supplier_without_required_type_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/suppliers', [
            'name' => 'Sem Tipo',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_create_supplier_with_invalid_type_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/suppliers', [
            'type' => 'EMPRESA',
            'name' => 'Tipo Inválido',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_create_supplier_without_name_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/suppliers', [
            'type' => 'PJ',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('name');
    }
}
