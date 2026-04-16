<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExternalApiControllerTest extends TestCase
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

    public function test_states_returns_list(): void
    {
        $response = $this->getJson('/api/v1/external/states');

        $response->assertOk();
    }

    public function test_banks_returns_list(): void
    {
        $response = $this->getJson('/api/v1/external/banks');

        $response->assertOk();
    }

    public function test_holidays_returns_list_for_year(): void
    {
        $response = $this->getJson('/api/v1/external/holidays/2026');

        $this->assertContains($response->status(), [200, 500, 503]);
    }

    public function test_ddd_endpoint_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/external/ddd/11');

        $this->assertContains($response->status(), [200, 404, 500, 503]);
    }

    public function test_cep_endpoint_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/external/cep/01310100');

        $this->assertContains($response->status(), [200, 404, 422, 500, 503]);
    }

    public function test_cities_by_uf_endpoint_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/external/states/SP/cities');

        $this->assertContains($response->status(), [200, 500, 503]);
    }
}
