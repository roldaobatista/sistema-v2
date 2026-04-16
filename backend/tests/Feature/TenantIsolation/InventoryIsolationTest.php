<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\Product;
use App\Models\Warehouse;

class InventoryIsolationTest extends TenantIsolationTestCase
{
    // ──────────────────────────────────────────────
    // Warehouses
    // ──────────────────────────────────────────────

    public function test_warehouses_index_only_returns_own_tenant(): void
    {
        app()->instance('current_tenant_id', $this->tenantA->id);
        Warehouse::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        Warehouse::factory()->count(2)->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAsTenantA();

        $response = $this->getJson('/api/v1/warehouses');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_cannot_access_other_tenant_warehouse(): void
    {
        $warehouse = $this->createForTenantB(Warehouse::class);

        $this->actingAsTenantA();

        $response = $this->getJson("/api/v1/warehouses/{$warehouse->id}");

        $response->assertNotFound();
    }

    public function test_cannot_delete_other_tenant_warehouse(): void
    {
        $warehouse = $this->createForTenantB(Warehouse::class);

        $this->actingAsTenantA();

        $response = $this->deleteJson("/api/v1/warehouses/{$warehouse->id}");

        $response->assertNotFound();
    }

    // ──────────────────────────────────────────────
    // Products
    // ──────────────────────────────────────────────

    public function test_products_index_only_returns_own_tenant(): void
    {
        app()->instance('current_tenant_id', $this->tenantA->id);
        Product::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        Product::factory()->count(2)->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAsTenantA();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_cannot_access_other_tenant_product(): void
    {
        $product = $this->createForTenantB(Product::class);

        $this->actingAsTenantA();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertNotFound();
    }
}
