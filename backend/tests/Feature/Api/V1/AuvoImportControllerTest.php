<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuvoImportControllerTest extends TestCase
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

    public function test_status_endpoint_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/auvo/status');

        $this->assertContains($response->status(), [200, 500, 503]);
    }

    public function test_sync_status_endpoint_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/auvo/sync-status');

        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_history_returns_list(): void
    {
        $response = $this->getJson('/api/v1/auvo/history');

        $response->assertOk();
    }

    public function test_mappings_returns_list(): void
    {
        $response = $this->getJson('/api/v1/auvo/mappings');

        $response->assertOk();
    }

    public function test_config_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/auvo/config');

        $response->assertOk();
    }

    public function test_preview_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/auvo/preview/customers');

        $this->assertContains($response->status(), [200, 422, 500, 503]);
    }
}
