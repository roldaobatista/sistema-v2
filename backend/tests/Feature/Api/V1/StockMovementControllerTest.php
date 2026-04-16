<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for StockMovementController (simple CRUD):
 *   GET    /api/v1/stock/movements  (via StockController::movements — same route)
 *   POST   /api/v1/stock/movements  (via StockController::store)
 *
 * Also tests the lighter StockMovementController directly where routed.
 */
class StockMovementControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Product $product;

    private Warehouse $warehouse;

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

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Armazém Teste',
            'code' => 'WH-TEST',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Movimento',
            'code' => 'PM-001',
            'unit' => 'un',
            'stock_qty' => 100,
            'cost_price' => 15.00,
            'is_active' => true,
        ]);
    }

    // ── Authentication ──────────────────────────────────────────

    public function test_unauthenticated_cannot_list_movements(): void
    {
        // Create a fresh request without Sanctum token
        $this->app['auth']->forgetGuards();
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')->getJson('/api/v1/stock/movements');
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_create_movement(): void
    {
        $this->app['auth']->forgetGuards();
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')->postJson('/api/v1/stock/movements', []);
        $response->assertUnauthorized();
    }

    // ── GET /api/v1/stock/movements ─────────────────────────────

    public function test_index_returns_paginated_movements(): void
    {
        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 10,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/stock/movements');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_search_by_product_name(): void
    {
        $response = $this->getJson('/api/v1/stock/movements?search=Produto');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_per_page_is_respected(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            StockMovement::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'type' => 'entry',
                'quantity' => $i,
                'created_by' => $this->user->id,
            ]);
        }

        $response = $this->getJson('/api/v1/stock/movements?per_page=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.per_page'));
    }

    // ── POST /api/v1/stock/movements ────────────────────────────

    public function test_store_manual_entry_creates_movement(): void
    {
        $payload = [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 20,
            'notes' => 'Entrada manual de teste',
        ];

        $response = $this->postJson('/api/v1/stock/movements', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'type', 'quantity']]);

        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'entry',
            'quantity' => 20,
        ]);
    }

    public function test_store_manual_exit_creates_movement(): void
    {
        // Ensure stock exists before exit
        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 50,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'exit',
            'quantity' => 5,
            'notes' => 'Saída manual de teste',
        ];

        $response = $this->postJson('/api/v1/stock/movements', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'exit',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/stock/movements', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'warehouse_id', 'type', 'quantity']);
    }

    public function test_store_validates_product_belongs_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignProduct = Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Product',
            'code' => 'FP-001',
            'unit' => 'un',
            'stock_qty' => 50,
        ]);

        $payload = [
            'product_id' => $foreignProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 10,
        ];

        $response = $this->postJson('/api/v1/stock/movements', $payload);

        // Must not allow cross-tenant product access
        $response->assertStatus(422);
    }

    public function test_store_validates_quantity_is_positive(): void
    {
        $payload = [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => -5,
        ];

        $response = $this->postJson('/api/v1/stock/movements', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_store_validates_type_enum(): void
    {
        $payload = [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'invalid_type',
            'quantity' => 10,
        ];

        $response = $this->postJson('/api/v1/stock/movements', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }
}
