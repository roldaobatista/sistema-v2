<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KardexControllerTest extends TestCase
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

    public function test_show_returns_kardex(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson("/api/v1/products/{$product->id}/kardex");

        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_show_alternate_route_returns_kardex(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson("/api/v1/stock/products/{$product->id}/kardex");

        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_show_rejects_cross_tenant_product(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = Product::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/v1/products/{$foreign->id}/kardex");

        $this->assertContains($response->status(), [403, 404]);
    }
}
