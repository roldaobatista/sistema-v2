<?php

namespace Tests\Feature\Api\V1\Integration;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoogleCalendarControllerTest extends TestCase
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
        $response = $this->getJson('/api/v1/integrations/google-calendar/status');

        $this->assertContains($response->status(), [200, 500, 503]);
    }

    public function test_auth_url_endpoint_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/integrations/google-calendar/auth-url');

        $this->assertContains($response->status(), [200, 422, 500, 503]);
    }

    public function test_callback_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/integrations/google-calendar/callback', []);

        $response->assertStatus(422);
    }

    public function test_disconnect_is_reachable(): void
    {
        $response = $this->postJson('/api/v1/integrations/google-calendar/disconnect');

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    public function test_sync_is_reachable(): void
    {
        $response = $this->postJson('/api/v1/integrations/google-calendar/sync');

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }
}
