<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_warehouses(): void
    {
        Warehouse::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::factory()->create();
        Warehouse::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson('/api/v1/warehouses');

        $response->assertOk()->assertJsonStructure(['data']);

        foreach ($response->json('data') as $row) {
            $this->assertEquals($this->tenant->id, $row['tenant_id']);
        }
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/v1/warehouses', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_creates_warehouse_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/warehouses', [
            'name' => 'Almoxarifado Central',
            'code' => 'ALM-001',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('warehouses', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Almoxarifado Central',
            'code' => 'ALM-001',
        ]);
    }

    public function test_show_returns_404_for_cross_tenant_warehouse(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = Warehouse::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/v1/warehouses/{$foreign->id}");

        $response->assertStatus(404);
    }

    public function test_update_rejects_cross_tenant_warehouse(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = Warehouse::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Original Name',
        ]);

        $response = $this->putJson("/api/v1/warehouses/{$foreign->id}", [
            'name' => 'Hijacked',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('warehouses', [
            'id' => $foreign->id,
            'name' => 'Original Name',
        ]);
    }
}
