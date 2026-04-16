<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductFieldsCompletenessTest extends TestCase
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

    public function test_can_create_product_with_all_new_fields(): void
    {
        $category = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

        $payload = [
            'name' => 'Produto Completo',
            'code' => 'COMP-001',
            'category_id' => $category->id,
            'default_supplier_id' => $supplier->id,
            'unit' => 'UN',
            'cost_price' => '10.50',
            'sell_price' => '25.00',
            'stock_qty' => '100',
            'stock_min' => '10',
            'min_repo_point' => '15',
            'max_stock' => '500',
            'track_stock' => true,
            'track_batch' => true,
            'track_serial' => false,
            'is_kit' => false,
            'manufacturer_code' => 'MFG-XYZ-123',
            'storage_location' => 'A-01-02',
            'ncm' => '84189900',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/products', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('products', [
            'name' => 'Produto Completo',
            'ncm' => '84189900',
            'default_supplier_id' => $supplier->id,
            'track_batch' => true,
            'track_serial' => false,
            'is_kit' => false,
        ]);
    }

    public function test_can_update_product_new_fields(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->putJson("/api/v1/products/{$product->id}", [
            'ncm' => '73181500',
            'default_supplier_id' => $supplier->id,
            'max_stock' => '200',
            'track_batch' => true,
            'track_serial' => true,
            'is_kit' => true,
            'min_repo_point' => '25',
        ]);

        $response->assertOk();
        $product->refresh();
        $this->assertEquals('73181500', $product->ncm);
        $this->assertEquals($supplier->id, $product->default_supplier_id);
        $this->assertTrue($product->track_batch);
        $this->assertTrue($product->track_serial);
        $this->assertTrue($product->is_kit);
    }

    public function test_show_endpoint_returns_default_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor Teste',
        ]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'default_supplier_id' => $supplier->id,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('default_supplier', $data);
        $this->assertEquals('Fornecedor Teste', $data['default_supplier']['name']);
    }

    public function test_show_endpoint_returns_ncm_field(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ncm' => '84189900',
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('84189900', $data['ncm']);
    }

    public function test_store_validates_ncm_max_length(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Teste NCM',
            'ncm' => '12345678901', // 11 chars, max is 10
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('ncm');
    }

    public function test_store_validates_default_supplier_id_exists(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Teste Supplier',
            'default_supplier_id' => 99999,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('default_supplier_id');
    }

    public function test_store_validates_max_stock_is_numeric_positive(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Teste Max Stock',
            'max_stock' => -5,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('max_stock');
    }

    public function test_fillable_includes_ncm_and_boolean_fields(): void
    {
        $product = new Product;
        $fillable = $product->getFillable();

        $this->assertContains('ncm', $fillable);
        $this->assertContains('track_stock', $fillable);
        $this->assertContains('track_batch', $fillable);
        $this->assertContains('track_serial', $fillable);
        $this->assertContains('is_kit', $fillable);
        $this->assertContains('max_stock', $fillable);
        $this->assertContains('default_supplier_id', $fillable);
        $this->assertContains('min_repo_point', $fillable);
    }

    public function test_default_supplier_relationship_returns_supplier(): void
    {
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'default_supplier_id' => $supplier->id,
        ]);

        $loaded = $product->defaultSupplier;

        $this->assertInstanceOf(Supplier::class, $loaded);
        $this->assertEquals($supplier->id, $loaded->id);
    }
}
