<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Inventory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    private Tenant $tenant;

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
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createInventory(?int $tenantId = null, ?int $warehouseId = null, string $status = Inventory::STATUS_OPEN): Inventory
    {
        return Inventory::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'warehouse_id' => $warehouseId ?? $this->warehouse->id,
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $this->createInventory(null, null, Inventory::STATUS_COMPLETED);

        $otherTenant = Tenant::factory()->create();
        $otherWarehouse = Warehouse::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->createInventory($otherTenant->id, $otherWarehouse->id);

        $response = $this->getJson('/api/v1/inventory/inventories');

        $response->assertOk()->assertJsonStructure(['data']);

        foreach ($response->json('data') as $inv) {
            $this->assertEquals($this->tenant->id, $inv['tenant_id']);
        }
    }

    public function test_store_validates_required_warehouse(): void
    {
        $response = $this->postJson('/api/v1/inventory/inventories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse_id']);
    }

    public function test_store_rejects_warehouse_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignWarehouse = Warehouse::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/inventory/inventories', [
            'warehouse_id' => $foreignWarehouse->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse_id']);
    }

    public function test_store_creates_inventory_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/inventory/inventories', [
            'warehouse_id' => $this->warehouse->id,
            'reference' => 'INV-2026-01',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('inventories', [
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => Inventory::STATUS_OPEN,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_rejects_duplicate_open_inventory_for_same_warehouse(): void
    {
        // Já existe um aberto
        $this->createInventory();

        $response = $this->postJson('/api/v1/inventory/inventories', [
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertStatus(422);
    }
}
