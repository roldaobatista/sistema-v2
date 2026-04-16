<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentModel;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentModelTest extends TestCase
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
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_paginated_equipment_models(): void
    {
        EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Modelo Toledo Prix 3',
            'brand' => 'Toledo',
            'category' => 'balanca',
        ]);

        $response = $this->getJson('/api/v1/equipment-models');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_index_search_by_name(): void
    {
        EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Toledo Prix 3',
            'brand' => 'Toledo',
        ]);
        EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Shimadzu UW',
            'brand' => 'Shimadzu',
        ]);

        $response = $this->getJson('/api/v1/equipment-models?search=Shimadzu');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        $this->assertCount(1, $items);
    }

    public function test_index_filter_by_category(): void
    {
        EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Model A',
            'category' => 'balanca',
        ]);
        EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Model B',
            'category' => 'termometro',
        ]);

        $response = $this->getJson('/api/v1/equipment-models?category=termometro');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals('termometro', $item['category']);
        }
    }

    public function test_store_creates_equipment_model(): void
    {
        $payload = [
            'name' => 'Novo Modelo XYZ',
            'brand' => 'MarcaTeste',
            'category' => 'balanca',
        ];

        $response = $this->postJson('/api/v1/equipment-models', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('equipment_models', [
            'name' => 'Novo Modelo XYZ',
            'brand' => 'MarcaTeste',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_fails_without_name(): void
    {
        $response = $this->postJson('/api/v1/equipment-models', [
            'brand' => 'Marca',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_validates_name_max_length(): void
    {
        $response = $this->postJson('/api/v1/equipment-models', [
            'name' => str_repeat('A', 200),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_show_returns_single_equipment_model(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Show Test Model',
            'brand' => 'ShowBrand',
        ]);

        $response = $this->getJson("/api/v1/equipment-models/{$model->id}");

        $response->assertOk();
    }

    public function test_update_modifies_equipment_model(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Name',
            'brand' => 'OrigBrand',
        ]);

        $response = $this->putJson("/api/v1/equipment-models/{$model->id}", [
            'name' => 'Updated Name',
            'brand' => 'NewBrand',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('equipment_models', [
            'id' => $model->id,
            'name' => 'Updated Name',
            'brand' => 'NewBrand',
        ]);
    }

    public function test_destroy_deletes_equipment_model(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ToDelete',
        ]);

        $response = $this->deleteJson("/api/v1/equipment-models/{$model->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('equipment_models', ['id' => $model->id]);
    }

    public function test_destroy_fails_when_equipments_are_linked(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'With Equipment',
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'equipment_model_id' => $model->id,
        ]);

        $response = $this->deleteJson("/api/v1/equipment-models/{$model->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('equipment_models', ['id' => $model->id]);
    }

    public function test_sync_products_attaches_products(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sync Products Model',
        ]);

        $product1 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Product 1',
            'code' => 'P001',
            'unit' => 'un',
            'stock_qty' => 0,
        ]);
        $product2 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Product 2',
            'code' => 'P002',
            'unit' => 'un',
            'stock_qty' => 0,
        ]);

        $response = $this->putJson("/api/v1/equipment-models/{$model->id}/products", [
            'product_ids' => [$product1->id, $product2->id],
        ]);

        $response->assertOk();
        $this->assertCount(2, $model->fresh()->products);
    }

    public function test_sync_products_ignores_products_from_other_tenant(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cross Tenant Sync Model',
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherProduct = Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Product',
            'code' => 'OTH-001',
            'unit' => 'un',
            'stock_qty' => 0,
        ]);

        $ownProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Own Product',
            'code' => 'OWN-001',
            'unit' => 'un',
            'stock_qty' => 0,
        ]);

        // Cross-tenant product should be rejected by validation
        $this->putJson("/api/v1/equipment-models/{$model->id}/products", [
            'product_ids' => [$ownProduct->id, $otherProduct->id],
        ])->assertUnprocessable();

        // Only same-tenant products should work
        $response = $this->putJson("/api/v1/equipment-models/{$model->id}/products", [
            'product_ids' => [$ownProduct->id],
        ]);

        $response->assertOk();
        $products = $model->fresh()->products;
        $this->assertCount(1, $products);
        $this->assertEquals($ownProduct->id, $products->first()->id);
    }

    public function test_sync_products_fails_without_product_ids(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sync Fail',
        ]);

        $response = $this->putJson("/api/v1/equipment-models/{$model->id}/products", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('product_ids');
    }

    public function test_index_includes_products_count(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Model With Products',
        ]);
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product',
            'code' => 'TP-001',
            'unit' => 'un',
            'stock_qty' => 0,
        ]);
        $model->products()->attach($product->id);

        $response = $this->getJson('/api/v1/equipment-models');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        $found = collect($items)->firstWhere('id', $model->id);
        $this->assertNotNull($found);
        $this->assertArrayHasKey('products_count', $found);
        $this->assertEquals(1, $found['products_count']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/equipment-models');

        $response->assertUnauthorized();
    }
}
