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

class ProductCrudTest extends TestCase
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
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_product_crud_and_tenant_isolation(): void
    {
        $create = $this->postJson('/api/v1/products', [
            'name' => 'Produto Master',
            'code' => 'PRD-MASTER-001',
            'sell_price' => 199.90,
            'tenant_id' => $this->otherTenant->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Produto Master')
            ->assertJsonPath('data.tenant_id', $this->tenant->id);

        $productId = (int) $create->json('data.id');

        Product::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Produto Outro Tenant',
            'code' => 'PRD-OTHER-001',
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.id', $productId);

        $foreignProduct = Product::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->otherTenant->id)
            ->firstOrFail();

        $this->getJson("/api/v1/products/{$foreignProduct->id}")
            ->assertStatus(404);

        $this->putJson("/api/v1/products/{$productId}", [
            'name' => 'Produto Master Atualizado',
            'sell_price' => 249.90,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Produto Master Atualizado');

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'name' => 'Produto Master Atualizado',
        ]);
    }
}
