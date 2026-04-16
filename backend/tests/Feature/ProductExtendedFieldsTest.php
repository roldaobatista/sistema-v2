<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductExtendedFieldsTest extends TestCase
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

    public function test_create_product_with_extended_fields(): void
    {
        $payload = [
            'name' => 'Produto Completo',
            'code' => 'PRD-EXT-001',
            'sell_price' => 199.90,
            'cost_price' => 99.50,
            'image_url' => 'https://example.com/product.jpg',
            'barcode' => '7891234567890',
            'brand' => 'Bosch',
            'weight' => 2.500,
            'width' => 30.00,
            'height' => 20.00,
            'depth' => 15.00,
        ];

        $response = $this->postJson('/api/v1/products', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Produto Completo')
            ->assertJsonPath('data.image_url', 'https://example.com/product.jpg')
            ->assertJsonPath('data.barcode', '7891234567890')
            ->assertJsonPath('data.brand', 'Bosch');

        $productId = (int) $response->json('data.id');

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'image_url' => 'https://example.com/product.jpg',
            'barcode' => '7891234567890',
            'brand' => 'Bosch',
        ]);
    }

    public function test_update_product_extended_fields(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Original',
        ]);

        $this->putJson("/api/v1/products/{$product->id}", [
            'brand' => 'Makita',
            'barcode' => '7890001234567',
            'weight' => 1.250,
            'width' => 15.50,
            'height' => 10.00,
            'depth' => 8.00,
        ])
            ->assertOk()
            ->assertJsonPath('data.brand', 'Makita')
            ->assertJsonPath('data.barcode', '7890001234567');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'brand' => 'Makita',
            'barcode' => '7890001234567',
        ]);
    }

    public function test_extended_fields_are_nullable(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produto Sem Campos Extras',
            'sell_price' => 50.00,
        ]);

        $response->assertCreated();

        $productId = (int) $response->json('data.id');

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'image_url' => null,
            'barcode' => null,
            'brand' => null,
            'weight' => null,
            'width' => null,
            'height' => null,
            'depth' => null,
        ]);
    }

    public function test_image_url_validation_rejects_invalid_url(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produto URL Inválida',
            'image_url' => 'nao-e-uma-url',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image_url']);
    }

    public function test_weight_rejects_negative_value(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produto Peso Negativo',
            'weight' => -5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['weight']);
    }

    public function test_volume_accessor(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'width' => 30.00,
            'height' => 20.00,
            'depth' => 10.00,
        ]);

        $this->assertEquals(6000.00, $product->volume);
    }

    public function test_volume_returns_null_without_dimensions(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'width' => null,
            'height' => 20.00,
            'depth' => 10.00,
        ]);

        $this->assertNull($product->volume);
    }

    public function test_import_fields_include_extended_fields(): void
    {
        $fields = Product::getImportFields();
        $keys = array_column($fields, 'key');

        $this->assertContains('barcode', $keys);
        $this->assertContains('brand', $keys);
        $this->assertContains('weight', $keys);
    }
}
