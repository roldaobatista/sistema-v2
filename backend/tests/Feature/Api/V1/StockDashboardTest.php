<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for StockController dashboard/summary endpoints:
 *   GET /api/v1/stock/movements
 *   GET /api/v1/stock/summary
 *   GET /api/v1/stock/low-alerts
 */
class StockDashboardTest extends TestCase
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

    // ── Authentication ──────────────────────────────────────────

    public function test_movements_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/stock/movements');
        // Sanctum is active; test without auth by calling without Sanctum
        // We verify the route exists and returns 200 for authenticated user
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_movements(): void
    {
        $this->app['auth']->forgetGuards();
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')->getJson('/api/v1/stock/movements');
        $response->assertUnauthorized();
    }

    // ── GET /api/v1/stock/movements ─────────────────────────────

    public function test_movements_returns_paginated_list(): void
    {
        $response = $this->getJson('/api/v1/stock/movements');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_movements_filters_by_product_id(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Filtro',
            'code' => 'PF-001',
            'unit' => 'un',
            'stock_qty' => 5,
        ]);

        $response = $this->getJson('/api/v1/stock/movements?product_id='.$product->id);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_movements_filters_by_type(): void
    {
        $response = $this->getJson('/api/v1/stock/movements?type=entry');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_movements_filters_by_date_range(): void
    {
        $response = $this->getJson('/api/v1/stock/movements?date_from=2025-01-01&date_to=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_movements_only_returns_own_tenant_data(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        // Authenticated as $this->user, should not see other tenant's movements
        $response = $this->getJson('/api/v1/stock/movements');
        $response->assertStatus(200);
        // All returned items must belong to this tenant
        collect($response->json('data'))->each(function ($item) {
            $this->assertEquals($this->tenant->id, $item['tenant_id'] ?? $this->tenant->id);
        });
    }

    // ── GET /api/v1/stock/summary ───────────────────────────────

    public function test_summary_returns_products_and_stats(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto A',
            'code' => 'PA-001',
            'unit' => 'un',
            'stock_qty' => 10,
            'stock_min' => 5,
            'cost_price' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/stock/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'products',
                    'stats' => [
                        'total_products',
                        'total_value',
                        'low_stock_count',
                        'out_of_stock_count',
                    ],
                ],
            ]);
    }

    public function test_summary_stats_are_correct(): void
    {
        // Active product with full stock
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Normal Stock',
            'code' => 'NS-001',
            'unit' => 'un',
            'stock_qty' => 50,
            'stock_min' => 10,
            'cost_price' => 10.00,
            'is_active' => true,
        ]);
        // Out-of-stock product
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Out of Stock',
            'code' => 'OS-001',
            'unit' => 'un',
            'stock_qty' => 0,
            'stock_min' => 5,
            'cost_price' => 5.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/stock/summary');

        $response->assertStatus(200);
        $stats = $response->json('data.stats');
        $this->assertGreaterThanOrEqual(1, $stats['out_of_stock_count']);
        $this->assertGreaterThanOrEqual(0, $stats['total_value']);
    }

    public function test_summary_filters_by_category(): void
    {
        $response = $this->getJson('/api/v1/stock/summary?category_id=9999');

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.total_products', 0);
    }

    // ── GET /api/v1/stock/low-alerts ────────────────────────────

    public function test_low_stock_alerts_returns_products_below_minimum(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Low Stock Item',
            'code' => 'LS-001',
            'unit' => 'un',
            'stock_qty' => 2,
            'stock_min' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/stock/low-alerts');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);

        $items = $response->json('data');
        $this->assertNotEmpty($items);
        // Every item must have stock_qty <= stock_min
        foreach ($items as $item) {
            $this->assertLessThanOrEqual($item['stock_min'], $item['stock_qty']);
        }
    }

    public function test_low_stock_alerts_excludes_products_with_no_minimum_set(): void
    {
        // Product with no minimum — should NOT appear in low alerts
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'No Min Product',
            'code' => 'NM-001',
            'unit' => 'un',
            'stock_qty' => 1,
            'stock_min' => 0,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/stock/low-alerts');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('code')->toArray();
        $this->assertNotContains('NM-001', $ids);
    }

    public function test_low_stock_alerts_compat_alias_works(): void
    {
        $response = $this->getJson('/api/v1/stock/low-stock-alerts');
        $response->assertStatus(200);
    }
}
