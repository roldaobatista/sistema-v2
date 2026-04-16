<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchControllerTest extends TestCase
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

    public function test_search_validates_required_fields(): void
    {
        $response = $this->getJson('/api/v1/search');

        $response->assertStatus(422);
    }

    public function test_search_returns_results_for_query(): void
    {
        $response = $this->getJson('/api/v1/search?q=teste');

        $response->assertOk();
    }

    public function test_search_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/search?q=balanca&type=all');

        $response->assertOk();
    }
}
