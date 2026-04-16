<?php

namespace Tests\Feature\Api\V1\Stock;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QrCodeInventoryTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $user;

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
        $this->otherTenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Almoxarifado Principal',
            'code' => 'WH-001',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'stock_qty' => 0,
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════
    // QR Code Scan — entry
    // ═══════════════════════════════════════════════════════════

    public function test_scan_entry_creates_stock_movement_by_qr_hash(): void
    {
        $product = $this->createProduct();
        DB::table('products')->where('id', $product->id)->update(['qr_hash' => 'QR-HASH-123']);

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'QR-HASH-123',
            'quantity' => 5,
            'type' => 'entry',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.product.name', $product->name);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'scanned_via_qr' => true,
        ]);
    }

    public function test_scan_entry_creates_stock_movement_by_label_payload(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'P'.$product->id,
            'quantity' => 3,
            'type' => 'entry',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.product.id', $product->id);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'entry',
            'scanned_via_qr' => true,
        ]);
    }

    public function test_scan_exit_creates_stock_movement(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'P'.$product->id,
            'quantity' => 2,
            'type' => 'exit',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'exit',
        ]);
    }

    public function test_scan_stores_custom_reference(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'P'.$product->id,
            'quantity' => 1,
            'type' => 'entry',
            'warehouse_id' => $this->warehouse->id,
            'reference' => 'OS-2026-050',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reference' => 'OS-2026-050',
        ]);
    }

    public function test_scan_uses_default_reference_when_empty(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'P'.$product->id,
            'quantity' => 1,
            'type' => 'entry',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reference' => 'Sincronização PWA via QR Code',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Validation
    // ═══════════════════════════════════════════════════════════

    public function test_scan_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/stock/scan-qr', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['qr_hash', 'quantity', 'type', 'warehouse_id']);
    }

    public function test_scan_validates_type_must_be_entry_or_exit(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'P'.$product->id,
            'quantity' => 1,
            'type' => 'transfer',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_scan_validates_quantity_must_be_positive(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'P'.$product->id,
            'quantity' => 0,
            'type' => 'entry',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_scan_validates_warehouse_must_exist(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'P'.$product->id,
            'quantity' => 1,
            'type' => 'entry',
            'warehouse_id' => 999999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['warehouse_id']);
    }

    // ═══════════════════════════════════════════════════════════
    // Product not found / Tenant isolation
    // ═══════════════════════════════════════════════════════════

    public function test_scan_returns_404_for_unknown_qr_hash(): void
    {
        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'UNKNOWN-HASH-XYZ',
            'quantity' => 1,
            'type' => 'entry',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertNotFound();
    }

    public function test_scan_returns_404_for_product_from_other_tenant(): void
    {
        $otherProduct = Product::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'stock_qty' => 0,
        ]);

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'P'.$otherProduct->id,
            'quantity' => 1,
            'type' => 'entry',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertNotFound();
    }

    public function test_scan_resolves_product_case_insensitive_payload(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'p'.$product->id,
            'quantity' => 1,
            'type' => 'entry',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.product.id', $product->id);
    }

    public function test_scan_returns_movement_and_product_data(): void
    {
        $product = $this->createProduct(['name' => 'Parafuso M8', 'code' => 'PRD-M8']);
        DB::table('products')->where('id', $product->id)->update(['qr_hash' => 'QR-M8']);

        $response = $this->postJson('/api/v1/stock/scan-qr', [
            'qr_hash' => 'QR-M8',
            'quantity' => 10,
            'type' => 'entry',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'movement',
                    'product' => ['id', 'name', 'sku'],
                ],
                'message',
            ])
            ->assertJsonPath('data.product.name', 'Parafuso M8')
            ->assertJsonPath('data.product.sku', 'PRD-M8');
    }
}
