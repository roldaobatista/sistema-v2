<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Módulo 8 — Estoque: Deep Audit Tests
 * Cobre: StockController, StockAdvancedController, isolamento multi-tenant,
 * movimentações, alertas de estoque mínimo, números de série, depósitos.
 */
class ProdutosEstoqueInventarioDeepAuditTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $tenantB;

    private User $user;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['status' => 'active']);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'stock@test.local',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Depósito Principal',
            'code' => 'DEP-01',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Teste',
            'stock_qty' => 50,
            'stock_min' => 5,
            'cost_price' => 10.00,
            'sell_price' => 15.00,
        ]);
    }

    // ══════════════════════════════════════════════
    // ── 401 UNAUTHENTICATED
    // ══════════════════════════════════════════════

    public function test_unauthenticated_cannot_access_stock_movements(): void
    {
        $this->getJson('/api/v1/stock/movements')->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_access_stock_summary(): void
    {
        $this->getJson('/api/v1/stock/summary')->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_access_low_stock_alerts(): void
    {
        $this->getJson('/api/v1/stock/low-stock-alerts')->assertUnauthorized();
    }

    // ══════════════════════════════════════════════
    // ── TENANT ISOLATION — MOVIMENTAÇÕES
    // ══════════════════════════════════════════════

    public function test_movements_list_only_shows_own_tenant(): void
    {
        $productB = Product::factory()->create(['tenant_id' => $this->tenantB->id]);

        // Movimento do próprio tenant
        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 10,
            'created_by' => $this->user->id,
        ]);

        $warehouseB = Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Depósito B Isolation',
            'code' => 'DEP-B-ISO',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        // Movimento do outro tenant
        StockMovement::create([
            'tenant_id' => $this->tenantB->id,
            'product_id' => $productB->id,
            'warehouse_id' => $warehouseB->id,
            'type' => 'entry',
            'quantity' => 5,
            'created_by' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock/movements');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertCount(1, $ids);
        $this->assertDatabaseHas('stock_movements', ['tenant_id' => $this->tenantB->id]);
    }

    // ══════════════════════════════════════════════
    // ── TENANT ISOLATION — LOW STOCK ALERTS
    // ══════════════════════════════════════════════

    public function test_low_stock_alerts_only_shows_own_tenant(): void
    {
        // Produto baixo estoque do próprio tenant
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Crítico Tenant A',
            'stock_qty' => 1,
            'stock_min' => 20,
            'is_active' => true,
        ]);

        // Produto baixo estoque do tenant B
        Product::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Crítico Tenant B',
            'stock_qty' => 1,
            'stock_min' => 20,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock/low-stock-alerts');
        $response->assertOk()->assertJsonStructure(['data', 'total']);

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Crítico Tenant A', $names);
        $this->assertNotContains('Crítico Tenant B', $names);
    }

    // ══════════════════════════════════════════════
    // ── TENANT ISOLATION — SUMMARY
    // ══════════════════════════════════════════════

    public function test_stock_summary_only_includes_own_tenant_products(): void
    {
        // Produto extra do tenant A
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'stock_qty' => 100,
            'cost_price' => 5.00,
        ]);

        // Produto do tenant B (não deve aparecer)
        Product::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'is_active' => true,
            'stock_qty' => 999,
            'cost_price' => 999.00,
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock/summary');
        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'products',
                'stats' => ['total_products', 'total_value', 'low_stock_count', 'out_of_stock_count'],
            ]]);

        // Apenas 2 produtos do tenant A (setUp + factory extra acima)
        $this->assertEquals(2, $response->json('data.stats.total_products'));

        // Valor total não deve incluir o produto do tenant B
        $expectedValue = round(50 * 10.00 + 100 * 5.00, 2);
        $this->assertEquals($expectedValue, $response->json('data.stats.total_value'));
    }

    // ══════════════════════════════════════════════
    // ── STOCK MOVEMENT — VALIDAÇÃO
    // ══════════════════════════════════════════════

    public function test_store_movement_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->postJson('/api/v1/stock/movements', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'warehouse_id', 'type', 'quantity']);
    }

    public function test_store_movement_rejects_cross_tenant_product(): void
    {
        $productB = Product::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->user, ['*']);
        $this->postJson('/api/v1/stock/movements', [
            'product_id' => $productB->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_store_movement_rejects_cross_tenant_warehouse(): void
    {
        $warehouseB = Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Depósito B',
            'code' => 'DEP-B',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $this->postJson('/api/v1/stock/movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouseB->id,
            'type' => 'entry',
            'quantity' => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['warehouse_id']);
    }

    public function test_store_movement_rejects_invalid_type(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->postJson('/api/v1/stock/movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'invalid_type',
            'quantity' => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_movement_rejects_zero_quantity(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->postJson('/api/v1/stock/movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    // ══════════════════════════════════════════════
    // ── STOCK MOVEMENT — HAPPY PATH
    // ══════════════════════════════════════════════

    public function test_store_entry_movement_creates_record(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $response = $this->postJson('/api/v1/stock/movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 20,
            'unit_cost' => 10.00,
            'notes' => 'Entrada de teste',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'data']);

        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 20,
        ]);
    }

    public function test_entry_movement_increases_product_stock_qty(): void
    {
        // Produto sem warehouse stock inicial — stock_qty parte de 0 no sistema de agregação
        $freshProduct = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_qty' => 0,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $this->postJson('/api/v1/stock/movements', [
            'product_id' => $freshProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 30,
        ])->assertCreated();

        $freshProduct->refresh();
        $this->assertEquals(30.0, (float) $freshProduct->stock_qty);
    }

    public function test_exit_movement_decreases_product_stock_qty(): void
    {
        // Produto sem warehouse stock — entrada primeiro, depois saída
        $freshProduct = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_qty' => 0,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        // Entrada de 50
        $this->postJson('/api/v1/stock/movements', [
            'product_id' => $freshProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 50,
        ])->assertCreated();

        // Saída de 15
        $this->postJson('/api/v1/stock/movements', [
            'product_id' => $freshProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'exit',
            'quantity' => 15,
        ])->assertCreated();

        $freshProduct->refresh();
        $this->assertEquals(35.0, (float) $freshProduct->stock_qty);
    }

    // ══════════════════════════════════════════════
    // ── LOW STOCK ALERTS — LÓGICA
    // ══════════════════════════════════════════════

    public function test_product_below_min_appears_in_low_stock_alerts(): void
    {
        $lowProd = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Parafuso Crítico',
            'stock_qty' => 3,
            'stock_min' => 10,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock/low-stock-alerts');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($lowProd->id, $ids);
    }

    public function test_product_above_min_not_in_low_stock_alerts(): void
    {
        // product do setUp: stock_qty=50, stock_min=5 → normal

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock/low-stock-alerts');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($this->product->id, $ids);
    }

    public function test_inactive_product_not_in_low_stock_alerts(): void
    {
        $inactive = Product::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'stock_qty' => 0,
            'stock_min' => 10,
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock/low-stock-alerts');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($inactive->id, $ids);
    }

    // ══════════════════════════════════════════════
    // ── SUMMARY — STATS CORRECTS
    // ══════════════════════════════════════════════

    public function test_summary_counts_low_stock_correctly(): void
    {
        // Baixo estoque (qty <= min)
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'stock_qty' => 2,
            'stock_min' => 10,
        ]);
        // Sem estoque
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'stock_qty' => 0,
            'stock_min' => 5,
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock/summary');
        $response->assertOk();

        $this->assertGreaterThanOrEqual(1, $response->json('data.stats.low_stock_count'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.stats.out_of_stock_count'));
    }

    // ══════════════════════════════════════════════
    // ── NÚMEROS DE SÉRIE
    // ══════════════════════════════════════════════

    public function test_unauthenticated_cannot_access_serial_numbers(): void
    {
        $this->getJson('/api/v1/stock/serial-numbers')->assertUnauthorized();
    }

    public function test_serial_numbers_list_returns_paginated_data(): void
    {
        ProductSerial::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'serial_number' => 'SN-ABC-001',
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock/serial-numbers');
        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data.0.serial_number', 'SN-ABC-001');
    }

    public function test_serial_numbers_only_shows_own_tenant(): void
    {
        $productB = Product::factory()->create(['tenant_id' => $this->tenantB->id]);
        ProductSerial::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'serial_number' => 'SN-TENANT-A',
            'status' => 'available',
        ]);
        ProductSerial::create([
            'tenant_id' => $this->tenantB->id,
            'product_id' => $productB->id,
            'serial_number' => 'SN-TENANT-B',
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock/serial-numbers');
        $response->assertOk();

        $serials = collect($response->json('data'))->pluck('serial_number')->toArray();
        $this->assertContains('SN-TENANT-A', $serials);
        $this->assertNotContains('SN-TENANT-B', $serials);
    }

    public function test_store_serial_number_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->postJson('/api/v1/stock/serial-numbers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'serial_number']);
    }

    public function test_store_serial_number_rejects_cross_tenant_product(): void
    {
        $productB = Product::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->user, ['*']);
        $this->postJson('/api/v1/stock/serial-numbers', [
            'product_id' => $productB->id,
            'serial_number' => 'SN-CROSS',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_store_serial_number_creates_with_available_status(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $response = $this->postJson('/api/v1/stock/serial-numbers', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'serial_number' => 'SN-NEW-001',
            'status' => 'available',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('product_serials', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'serial_number' => 'SN-NEW-001',
            'status' => 'available',
        ]);
    }

    public function test_duplicate_serial_number_within_tenant_rejected(): void
    {
        ProductSerial::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'serial_number' => 'SN-DUPLICATE',
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $this->postJson('/api/v1/stock/serial-numbers', [
            'product_id' => $this->product->id,
            'serial_number' => 'SN-DUPLICATE',
        ])->assertUnprocessable();
    }

    // ══════════════════════════════════════════════
    // ── DEPÓSITOS (WAREHOUSES)
    // ══════════════════════════════════════════════

    public function test_warehouse_list_only_shows_own_tenant(): void
    {
        Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Depósito Tenant B',
            'code' => 'DEP-B',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock/warehouses');
        $response->assertOk();

        $names = collect($response->json('data') ?? $response->json())->pluck('name')->toArray();
        $this->assertContains('Depósito Principal', $names);
        $this->assertNotContains('Depósito Tenant B', $names);
    }

    // ══════════════════════════════════════════════
    // ── AUTO REORDER — BUG FIX: min_repo_point
    // ══════════════════════════════════════════════

    public function test_auto_reorder_suggestions_returns_products_below_min_repo_point(): void
    {
        // Produto com min_repo_point preenchido e stock abaixo
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'stock_qty' => 5,
            'min_repo_point' => 20,  // campo correto (não reorder_point)
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock-advanced/auto-reorder/suggestions');
        $response->assertOk()
            ->assertJsonStructure(['data' => ['products_below_reorder', 'suggestions']]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.products_below_reorder'));
    }

    public function test_auto_reorder_excludes_products_above_min_repo_point(): void
    {
        // Produto com estoque OK — não deve aparecer
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'stock_qty' => 100,
            'min_repo_point' => 10,
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock-advanced/auto-reorder/suggestions');
        $response->assertOk();

        // Nenhuma sugestão para produto com stock_qty > min_repo_point
        $suggestions = $response->json('data.suggestions');
        $this->assertEmpty($suggestions);
    }

    // ══════════════════════════════════════════════
    // ── INVENTORIES (Contagem de Inventário)
    // ══════════════════════════════════════════════

    public function test_inventory_list_returns_ok(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->getJson('/api/v1/stock/inventories')->assertOk();
    }

    public function test_inventory_store_requires_warehouse_id(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->postJson('/api/v1/stock/inventories', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['warehouse_id']);
    }

    public function test_inventory_store_with_valid_warehouse_creates_record(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $response = $this->postJson('/api/v1/stock/inventories', [
            'warehouse_id' => $this->warehouse->id,
        ]);

        // 201 Created ou 200 OK dependendo do controller
        $this->assertContains($response->status(), [200, 201]);
    }

    // ══════════════════════════════════════════════
    // ── WARRANTY LOOKUP — BUG FIX: warranty_end_at
    // ══════════════════════════════════════════════

    public function test_warranty_lookup_returns_correct_structure(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock-advanced/warranty/lookup');

        // Deve retornar OK com estrutura correta (bug corrigido: warranty_end_at)
        $response->assertOk()
            ->assertJsonStructure(['data' => ['total', 'active', 'expired', 'warranties']]);
    }

    // ══════════════════════════════════════════════
    // ── SLOW MOVING ANALYSIS
    // ══════════════════════════════════════════════

    public function test_slow_moving_analysis_returns_expected_structure(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $response = $this->getJson('/api/v1/stock-advanced/slow-moving');
        $response->assertOk()
            ->assertJsonStructure(['data' => ['period_days', 'slow_moving_count', 'total_capital_locked', 'products']]);
    }
}
