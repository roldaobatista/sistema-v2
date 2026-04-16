<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Service;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogItem;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogControllerTest extends TestCase
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

    private function createCatalog(array $overrides = []): ServiceCatalog
    {
        return ServiceCatalog::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Catalogo de Servicos',
            'slug' => 'catalogo-'.uniqid(),
            'subtitle' => 'Subtitle',
            'is_published' => true,
        ], $overrides));
    }

    private function createService(array $overrides = []): Service
    {
        $cat = ServiceCategory::factory()->create(['tenant_id' => $overrides['tenant_id'] ?? $this->tenant->id]);

        return Service::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'category_id' => $cat->id,
        ], $overrides));
    }

    private function createItem(ServiceCatalog $catalog, array $overrides = []): ServiceCatalogItem
    {
        return ServiceCatalogItem::create(array_merge([
            'service_catalog_id' => $catalog->id,
            'title' => 'Item de Servico',
            'description' => 'Descricao do item',
            'sort_order' => 0,
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════
    // Public Show (no auth)
    // ═══════════════════════════════════════════════════════════

    public function test_public_show_returns_published_catalog_by_slug(): void
    {
        $catalog = $this->createCatalog([
            'slug' => 'meu-catalogo-publico',
            'is_published' => true,
            'name' => 'Catalogo Publico',
        ]);
        $service = $this->createService();
        $this->createItem($catalog, ['title' => 'Item 1', 'service_id' => $service->id]);

        $response = $this->getJson('/api/v1/catalog/meu-catalogo-publico');

        $response->assertOk()
            ->assertJsonPath('data.catalog.name', 'Catalogo Publico')
            ->assertJsonPath('data.catalog.slug', 'meu-catalogo-publico')
            ->assertJsonStructure([
                'data' => [
                    'catalog' => ['id', 'name', 'slug', 'subtitle', 'header_description'],
                    'items',
                ],
            ]);
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_public_show_returns_404_for_unpublished_catalog(): void
    {
        $this->createCatalog([
            'slug' => 'unpublished-slug',
            'is_published' => false,
        ]);

        $response = $this->getJson('/api/v1/catalog/unpublished-slug');

        $response->assertNotFound();
    }

    public function test_public_show_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->getJson('/api/v1/catalog/slug-inexistente');

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // Admin — Catalog CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_index_returns_catalogs_with_item_count(): void
    {
        $catalog = $this->createCatalog();
        $this->createItem($catalog);
        $this->createItem($catalog, ['title' => 'Item 2', 'sort_order' => 1]);

        $response = $this->getJson('/api/v1/catalogs');

        $response->assertOk()
            ->assertJsonStructure(['data']);
        $found = collect($response->json('data'))->firstWhere('id', $catalog->id);
        $this->assertNotNull($found);
        $this->assertEquals(2, $found['items_count']);
    }

    public function test_store_creates_catalog_with_slug(): void
    {
        $response = $this->postJson('/api/v1/catalogs', [
            'name' => 'Novo Catalogo',
            'subtitle' => 'Sub',
            'is_published' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Novo Catalogo');

        $slug = $response->json('data.slug');
        $this->assertStringStartsWith('novo-catalogo', $slug);

        $this->assertDatabaseHas('service_catalogs', [
            'name' => 'Novo Catalogo',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_uses_custom_slug_when_provided(): void
    {
        $response = $this->postJson('/api/v1/catalogs', [
            'name' => 'Teste',
            'slug' => 'custom-slug',
        ]);

        $response->assertStatus(201);
        $slug = $response->json('data.slug');
        $this->assertStringStartsWith('custom-slug', $slug);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/v1/catalogs', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_slug_format(): void
    {
        $response = $this->postJson('/api/v1/catalogs', [
            'name' => 'Test',
            'slug' => 'Invalid Slug!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_modifies_catalog(): void
    {
        $catalog = $this->createCatalog(['name' => 'Old']);

        $response = $this->putJson("/api/v1/catalogs/{$catalog->id}", [
            'name' => 'Updated Catalog',
            'is_published' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Catalog');
    }

    public function test_destroy_deletes_catalog_and_its_items(): void
    {
        Storage::fake('public');
        $catalog = $this->createCatalog();
        $item1 = $this->createItem($catalog);
        $item2 = $this->createItem($catalog, ['title' => 'Item 2', 'sort_order' => 1]);

        $response = $this->deleteJson("/api/v1/catalogs/{$catalog->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('service_catalogs', ['id' => $catalog->id]);
        $this->assertDatabaseMissing('service_catalog_items', ['id' => $item1->id]);
        $this->assertDatabaseMissing('service_catalog_items', ['id' => $item2->id]);
    }

    // ═══════════════════════════════════════════════════════════
    // Admin — Catalog Items
    // ═══════════════════════════════════════════════════════════

    public function test_items_returns_ordered_items_with_service(): void
    {
        $catalog = $this->createCatalog();
        $service = $this->createService(['name' => 'Svc X']);
        $this->createItem($catalog, ['title' => 'Second', 'sort_order' => 2, 'service_id' => $service->id]);
        $this->createItem($catalog, ['title' => 'First', 'sort_order' => 1]);

        $response = $this->getJson("/api/v1/catalogs/{$catalog->id}/items");

        $response->assertOk()
            ->assertJsonStructure(['data']);
        $titles = collect($response->json('data'))->pluck('title')->values()->all();
        $this->assertEquals('First', $titles[0]);
        $this->assertEquals('Second', $titles[1]);
    }

    public function test_store_item_creates_item_in_catalog(): void
    {
        $catalog = $this->createCatalog();
        $service = $this->createService();

        $response = $this->postJson("/api/v1/catalogs/{$catalog->id}/items", [
            'title' => 'Novo Item',
            'description' => 'Descricao',
            'service_id' => $service->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Novo Item');

        $this->assertDatabaseHas('service_catalog_items', [
            'service_catalog_id' => $catalog->id,
            'title' => 'Novo Item',
            'service_id' => $service->id,
        ]);
    }

    public function test_store_item_auto_increments_sort_order(): void
    {
        $catalog = $this->createCatalog();
        $this->createItem($catalog, ['sort_order' => 5]);

        $response = $this->postJson("/api/v1/catalogs/{$catalog->id}/items", [
            'title' => 'Auto Order Item',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(6, $response->json('data.sort_order'));
    }

    public function test_store_item_validates_required_title(): void
    {
        $catalog = $this->createCatalog();

        $response = $this->postJson("/api/v1/catalogs/{$catalog->id}/items", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_item_validates_service_belongs_to_tenant(): void
    {
        $catalog = $this->createCatalog();
        $otherService = $this->createService(['tenant_id' => $this->otherTenant->id]);

        $response = $this->postJson("/api/v1/catalogs/{$catalog->id}/items", [
            'title' => 'Item',
            'service_id' => $otherService->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id']);
    }

    public function test_update_item_modifies_item(): void
    {
        $catalog = $this->createCatalog();
        $item = $this->createItem($catalog, ['title' => 'Old Title']);

        $response = $this->putJson("/api/v1/catalogs/{$catalog->id}/items/{$item->id}", [
            'title' => 'New Title',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'New Title');
    }

    public function test_update_item_returns_404_if_item_not_in_catalog(): void
    {
        $catalog1 = $this->createCatalog();
        $catalog2 = $this->createCatalog(['slug' => 'other-catalog']);
        $item = $this->createItem($catalog2);

        $response = $this->putJson("/api/v1/catalogs/{$catalog1->id}/items/{$item->id}", [
            'title' => 'Should Fail',
        ]);

        $response->assertNotFound();
    }

    public function test_destroy_item_deletes_item(): void
    {
        $catalog = $this->createCatalog();
        $item = $this->createItem($catalog);

        $response = $this->deleteJson("/api/v1/catalogs/{$catalog->id}/items/{$item->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('service_catalog_items', ['id' => $item->id]);
    }

    public function test_destroy_item_returns_404_if_item_not_in_catalog(): void
    {
        $catalog1 = $this->createCatalog();
        $catalog2 = $this->createCatalog(['slug' => 'another-catalog']);
        $item = $this->createItem($catalog2);

        $response = $this->deleteJson("/api/v1/catalogs/{$catalog1->id}/items/{$item->id}");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // Image Upload
    // ═══════════════════════════════════════════════════════════

    public function test_upload_image_stores_file_and_updates_item(): void
    {
        Storage::fake('public');
        $catalog = $this->createCatalog();
        $item = $this->createItem($catalog);

        $file = UploadedFile::fake()->image('service.jpg', 400, 400);

        $response = $this->postJson(
            "/api/v1/catalogs/{$catalog->id}/items/{$item->id}/image",
            ['image' => $file]
        );

        $response->assertOk()
            ->assertJsonStructure(['data' => ['image_url', 'image_path']]);

        $path = $response->json('data.image_path');
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_image_validates_file_type(): void
    {
        Storage::fake('public');
        $catalog = $this->createCatalog();
        $item = $this->createItem($catalog);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->postJson(
            "/api/v1/catalogs/{$catalog->id}/items/{$item->id}/image",
            ['image' => $file]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_upload_image_returns_404_if_item_not_in_catalog(): void
    {
        Storage::fake('public');
        $catalog1 = $this->createCatalog();
        $catalog2 = $this->createCatalog(['slug' => 'cat-img']);
        $item = $this->createItem($catalog2);

        $file = UploadedFile::fake()->image('pic.jpg');

        $response = $this->postJson(
            "/api/v1/catalogs/{$catalog1->id}/items/{$item->id}/image",
            ['image' => $file]
        );

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // Reorder Items
    // ═══════════════════════════════════════════════════════════

    public function test_reorder_items_updates_sort_order(): void
    {
        $catalog = $this->createCatalog();
        $item1 = $this->createItem($catalog, ['title' => 'A', 'sort_order' => 0]);
        $item2 = $this->createItem($catalog, ['title' => 'B', 'sort_order' => 1]);
        $item3 = $this->createItem($catalog, ['title' => 'C', 'sort_order' => 2]);

        // Reverse order: C, B, A
        $response = $this->postJson("/api/v1/catalogs/{$catalog->id}/reorder", [
            'item_ids' => [$item3->id, $item2->id, $item1->id],
        ]);

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->values()->all();
        $this->assertEquals(['C', 'B', 'A'], $titles);
    }

    public function test_reorder_items_validates_required_item_ids(): void
    {
        $catalog = $this->createCatalog();

        $response = $this->postJson("/api/v1/catalogs/{$catalog->id}/reorder", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['item_ids']);
    }

    public function test_reorder_items_validates_item_ids_belong_to_catalog(): void
    {
        $catalog1 = $this->createCatalog();
        $catalog2 = $this->createCatalog(['slug' => 'reorder-other']);
        $foreignItem = $this->createItem($catalog2);

        $response = $this->postJson("/api/v1/catalogs/{$catalog1->id}/reorder", [
            'item_ids' => [$foreignItem->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['item_ids.0']);
    }
}
