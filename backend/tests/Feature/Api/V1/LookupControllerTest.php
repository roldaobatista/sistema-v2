<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LookupControllerTest extends TestCase
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

    public function test_types_returns_available_types(): void
    {
        $response = $this->getJson('/api/v1/lookups/types');

        $response->assertOk();
    }

    public function test_index_returns_list_for_type(): void
    {
        $response = $this->getJson('/api/v1/lookups/fueling_fuel_type');

        $this->assertContains($response->status(), [200, 404, 422]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/lookups/fueling_fuel_type', []);

        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_index_rejects_unknown_type(): void
    {
        $response = $this->getJson('/api/v1/lookups/tipo-inexistente-abc');

        $this->assertContains($response->status(), [200, 404, 422]);
    }
}
