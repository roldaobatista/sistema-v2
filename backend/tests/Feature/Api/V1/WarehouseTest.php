<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseTest extends TestCase
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

    public function test_index_returns_paginated_warehouses(): void
    {
        Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Warehouse Test',
            'code' => 'WH-001',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/warehouses');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_index_search_filter(): void
    {
        Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Armazém Central',
            'code' => 'CENTRAL',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);
        Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Caminhão 01',
            'code' => 'CAM-01',
            'type' => Warehouse::TYPE_VEHICLE,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/warehouses?search=Central');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        $this->assertCount(1, $items);
        $this->assertStringContainsString('Central', $items[0]['name']);
    }

    public function test_index_filter_by_type(): void
    {
        Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fixed WH',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);
        Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vehicle WH',
            'type' => Warehouse::TYPE_VEHICLE,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/warehouses?type=vehicle');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals('vehicle', $item['type']);
        }
    }

    public function test_store_creates_warehouse(): void
    {
        $response = $this->postJson('/api/v1/warehouses', [
            'name' => 'Novo Armazém',
            'code' => 'NOVO-001',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('warehouses', [
            'name' => 'Novo Armazém',
            'code' => 'NOVO-001',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_fails_without_name(): void
    {
        $response = $this->postJson('/api/v1/warehouses', [
            'code' => 'NO-NAME',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_show_returns_single_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Show WH',
            'code' => 'SHOW-001',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/warehouses/{$warehouse->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Show WH')
            ->assertJsonPath('data.code', 'SHOW-001')
            ->assertJsonPath('data.type', 'fixed')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_show_rejects_warehouse_from_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherWh = Warehouse::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant WH',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/warehouses/{$otherWh->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
            'code' => 'OLD-001',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/warehouses/{$warehouse->id}", [
            'name' => 'New Name',
            'code' => 'NEW-001',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'New Name',
        ]);
    }

    public function test_destroy_deletes_warehouse_without_stock(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Empty WH',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/warehouses/{$warehouse->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id, 'deleted_at' => null]);
    }

    public function test_destroy_fails_when_warehouse_has_stock(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Stocked WH',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Stock Product',
            'code' => 'SP-001',
            'unit' => 'un',
            'stock_qty' => 10,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 50,
        ]);

        $response = $this->deleteJson("/api/v1/warehouses/{$warehouse->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }

    public function test_index_active_only_filter(): void
    {
        Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active WH',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => true,
        ]);
        Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive WH',
            'type' => Warehouse::TYPE_FIXED,
            'is_active' => false,
        ]);

        // active_only defaults to true
        $response = $this->getJson('/api/v1/warehouses');
        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertTrue($item['is_active']);
        }

        // with active_only=false, should include inactive
        $response2 = $this->getJson('/api/v1/warehouses?active_only=false');
        $response2->assertOk();
        $items2 = $response2->json('data.data') ?? $response2->json('data');
        $hasInactive = collect($items2)->contains(fn ($i) => ! $i['is_active']);
        $this->assertTrue($hasInactive);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/warehouses');

        $response->assertUnauthorized();
    }

    public function test_unauthorized_user_cannot_create_warehouse_403(): void
    {
        // Remove CheckPermission from withoutMiddleware to test the actual authorization
        $this->withMiddleware([CheckPermission::class]);

        // Define a route explicitly for the test context since we might be hitting it directly
        // The API actually uses `Route::post('warehouses')` protected by `estoque.warehouse.create`

        $response = $this->postJson('/api/v1/warehouses', [
            'name' => 'Novo Armazém 403',
            'code' => '403-WH',
            'is_active' => true,
        ]);

        $response->assertStatus(403);
    }
}
