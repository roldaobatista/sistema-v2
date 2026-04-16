<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for purchase order (auto-reorder) functionality via StockAdvancedController.
 *
 * There is no dedicated PurchaseOrderController. Purchase quotations (POs) are
 * created through the auto-reorder flow in StockAdvancedController.
 *
 * Routes (from routes/api/advanced-lots.php, prefix: stock-advanced):
 *   GET    /api/v1/stock-advanced/auto-reorder/suggestions
 *   POST   /api/v1/stock-advanced/auto-reorder/create
 */
class PurchaseOrderControllerTest extends TestCase
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

    // ── GET auto-reorder suggestions ───────────────────────────

    public function test_can_get_auto_reorder_suggestions(): void
    {
        // Product with stock below minimum triggers a suggestion
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_qty' => 2,
            'stock_min' => 10,
            'track_stock' => true,
        ]);

        $response = $this->getJson('/api/v1/stock-advanced/auto-reorder/suggestions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'products_below_reorder',
                'suggestions',
            ],
        ]);
    }

    public function test_suggestions_reflect_low_stock_products(): void
    {
        // autoReorder uses min_repo_point (reorder point), not stock_min
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Crítico',
            'stock_qty' => 0,
            'min_repo_point' => 5,
            'is_active' => true,
            'track_stock' => true,
        ]);

        // Product with adequate stock should NOT appear
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto OK',
            'stock_qty' => 50,
            'min_repo_point' => 5,
            'is_active' => true,
            'track_stock' => true,
        ]);

        $response = $this->getJson('/api/v1/stock-advanced/auto-reorder/suggestions');

        $response->assertStatus(200);
        $count = $response->json('data.products_below_reorder');
        $this->assertGreaterThanOrEqual(1, $count);
    }

    // ── POST auto-reorder create ───────────────────────────────

    public function test_can_create_purchase_quotation_via_auto_reorder(): void
    {
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
        ]);

        $response = $this->postJson('/api/v1/stock-advanced/auto-reorder/create', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'supplier_id' => $supplier->id,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['purchase_quotation_ids'],
        ]);

        $ids = $response->json('data.purchase_quotation_ids');
        $this->assertCount(1, $ids);
    }

    public function test_auto_reorder_groups_by_supplier(): void
    {
        $supplier1 = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $supplier2 = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $product1 = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $product2 = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/stock-advanced/auto-reorder/create', [
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 5, 'supplier_id' => $supplier1->id],
                ['product_id' => $product2->id, 'quantity' => 3, 'supplier_id' => $supplier2->id],
            ],
        ]);

        $response->assertStatus(201);
        $ids = $response->json('data.purchase_quotation_ids');
        // Two different suppliers → two separate purchase quotations
        $this->assertCount(2, $ids);
    }

    public function test_auto_reorder_requires_items(): void
    {
        $response = $this->postJson('/api/v1/stock-advanced/auto-reorder/create', [
            'items' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_auto_reorder_requires_valid_product_id(): void
    {
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/stock-advanced/auto-reorder/create', [
            'items' => [
                ['product_id' => 999999, 'quantity' => 5, 'supplier_id' => $supplier->id],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_auto_reorder_requires_valid_supplier_id(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/stock-advanced/auto-reorder/create', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'supplier_id' => 999999],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.supplier_id']);
    }
}
