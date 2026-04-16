<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockTransferControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Warehouse $fromWarehouse;

    private Warehouse $toWarehouse;

    private Product $product;

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

        $this->fromWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->toWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createTransfer(?int $tenantId = null, string $status = 'pending_acceptance'): StockTransfer
    {
        return StockTransfer::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_index_returns_only_current_tenant_transfers(): void
    {
        $this->createTransfer();

        $otherTenant = Tenant::factory()->create();
        $this->createTransfer($otherTenant->id);

        $response = $this->getJson('/api/v1/stock/transfers');

        $response->assertOk()->assertJsonStructure(['data']);

        foreach ($response->json('data') as $row) {
            $this->assertEquals($this->tenant->id, $row['tenant_id']);
        }
    }

    public function test_index_filters_by_status(): void
    {
        $this->createTransfer(null, StockTransfer::STATUS_PENDING_ACCEPTANCE);
        $this->createTransfer(null, 'completed');

        $response = $this->getJson('/api/v1/stock/transfers?status=completed');

        $response->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertSame('completed', $row['status']);
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/stock/transfers', []);

        $response->assertStatus(422);
    }

    public function test_show_returns_404_for_cross_tenant_transfer(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createTransfer($otherTenant->id);

        $response = $this->getJson("/api/v1/stock/transfers/{$foreign->id}");

        $response->assertStatus(404);
    }

    public function test_my_pending_filter_returns_transfers_assigned_to_user(): void
    {
        StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_user_id' => $this->user->id,
            'status' => StockTransfer::STATUS_PENDING_ACCEPTANCE,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/stock/transfers?my_pending=1');

        $response->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertEquals($this->user->id, $row['to_user_id']);
            $this->assertEquals(StockTransfer::STATUS_PENDING_ACCEPTANCE, $row['status']);
        }
    }
}
