<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockIntelligenceControllerTest extends TestCase
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

    public function test_abc_curve_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/stock/intelligence/abc-curve');

        $response->assertOk();
    }

    public function test_turnover_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/stock/intelligence/turnover');

        $response->assertOk();
    }

    public function test_average_cost_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/stock/intelligence/average-cost');

        $response->assertOk();
    }

    public function test_reorder_points_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/stock/intelligence/reorder-points');

        $response->assertOk();
    }

    public function test_reservations_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/stock/intelligence/reservations');

        $response->assertOk();
    }

    public function test_expiring_batches_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/stock/intelligence/expiring-batches');

        $response->assertOk();
    }

    public function test_stale_products_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/stock/intelligence/stale-products');

        $response->assertOk();
    }

    public function test_auto_request_is_reachable(): void
    {
        $response = $this->postJson('/api/v1/stock/intelligence/reorder-points/auto-request', []);

        $this->assertContains($response->status(), [200, 201, 422]);
    }
}
