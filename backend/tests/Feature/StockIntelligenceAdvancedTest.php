<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockIntelligenceAdvancedTest extends TestCase
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

    public function test_expiring_batches_returns_batches_near_expiry(): void
    {

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto com Lote',
            'track_batch' => true,
        ]);

        Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'LOT-EXPIRING',
            'expires_at' => now()->addDays(15),
        ]);

        Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'LOT-SAFE',
            'expires_at' => now()->addDays(60),
        ]);

        $response = $this->getJson('/api/v1/stock/intelligence/expiring-batches?days=30');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'summary' => ['expiring_count', 'already_expired', 'filter_days'],
            ]);

        $data = $response->json('data');
        $batchCodes = array_column($data, 'code');

        $this->assertContains('LOT-EXPIRING', $batchCodes);
        $this->assertNotContains('LOT-SAFE', $batchCodes);
    }

    public function test_stale_products_returns_products_without_movement(): void
    {
        $staleProduct = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Parado',
            'stock_qty' => 50,
            'cost_price' => 10.00,
            'is_active' => true,
        ]);

        $activeProduct = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Ativo',
            'stock_qty' => 30,
            'cost_price' => 20.00,
            'is_active' => true,
        ]);

        // Cria movimentação recente para o produto ativo
        StockMovement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $activeProduct->id,
            'type' => 'exit',
            'quantity' => -5,
            'unit_cost' => 20.00,
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->getJson('/api/v1/stock/intelligence/stale-products?days=90');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'summary' => ['stale_count', 'total_stale_value', 'filter_days'],
            ]);

        $data = $response->json('data');
        $names = array_column($data, 'name');

        $this->assertContains('Produto Parado', $names);
        $this->assertNotContains('Produto Ativo', $names);
    }

    public function test_expiring_batches_excludes_already_expired(): void
    {

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Lote Vencido',
            'track_batch' => true,
        ]);

        Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'code' => 'LOT-EXPIRED',
            'expires_at' => now()->subDays(5),
        ]);

        $response = $this->getJson('/api/v1/stock/intelligence/expiring-batches?days=30');

        $response->assertOk();

        $data = $response->json('data');
        $batchCodes = array_column($data, 'code');

        $this->assertNotContains('LOT-EXPIRED', $batchCodes);
        // Mas deve contar nos already_expired do summary
        $this->assertGreaterThanOrEqual(1, $response->json('summary.already_expired'));
    }

    public function test_stale_products_excludes_zero_stock(): void
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Zerado',
            'stock_qty' => 0,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/stock/intelligence/stale-products?days=90');

        $response->assertOk();

        $names = array_column($response->json('data'), 'name');
        $this->assertNotContains('Produto Zerado', $names);
    }
}
