<?php

namespace Tests\Feature\Api\V1\Master;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_products(): void
    {
        $mine = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::factory()->create();
        $foreign = Product::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()->assertJsonStructure(['data']);
        $rows = $response->json('data.data') ?? $response->json('data');
        $ids = collect($rows)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/products', []);

        $response->assertStatus(422);
    }

    public function test_show_returns_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
    }

    public function test_show_rejects_cross_tenant_product(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = Product::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/v1/products/{$foreign->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_categories_returns_list(): void
    {
        $response = $this->getJson('/api/v1/product-categories');

        $response->assertOk();
    }

    public function test_store_category_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/product-categories', []);

        $response->assertStatus(422);
    }
}
