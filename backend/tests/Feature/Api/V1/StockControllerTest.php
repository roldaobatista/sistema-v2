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

class StockControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_movements_returns_only_current_tenant(): void
    {
        StockMovement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 10,
            'created_by' => $this->user->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherWarehouse = Warehouse::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        StockMovement::factory()->create([
            'tenant_id' => $otherTenant->id,
            'product_id' => $otherProduct->id,
            'warehouse_id' => $otherWarehouse->id,
            'type' => 'entry',
            'quantity' => 99,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/stock/movements');

        $response->assertOk()->assertJsonStructure(['data']);

        foreach ($response->json('data') as $mov) {
            $this->assertEquals($this->tenant->id, $mov['tenant_id']);
        }
    }

    public function test_movements_filters_by_type(): void
    {
        StockMovement::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 5,
            'created_by' => $this->user->id,
        ]);
        StockMovement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'exit',
            'quantity' => 1,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/stock/movements?type=entry');

        $response->assertOk();
        foreach ($response->json('data') as $mov) {
            $this->assertSame('entry', $mov['type']);
        }
    }

    public function test_summary_returns_200(): void
    {
        $response = $this->getJson('/api/v1/stock/summary');

        $response->assertOk();
    }

    public function test_low_alerts_returns_200(): void
    {
        $response = $this->getJson('/api/v1/stock/low-alerts');

        $response->assertOk();
    }

    public function test_movements_cross_tenant_count(): void
    {
        // 3 movs no tenant atual
        StockMovement::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 10,
            'created_by' => $this->user->id,
        ]);

        // 5 movs em outro tenant
        $otherTenant = Tenant::factory()->create();
        $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherWarehouse = Warehouse::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        StockMovement::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'product_id' => $otherProduct->id,
            'warehouse_id' => $otherWarehouse->id,
            'type' => 'entry',
            'quantity' => 20,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/stock/movements');

        $response->assertOk();
        $this->assertSame(3, count($response->json('data')));
    }
}
