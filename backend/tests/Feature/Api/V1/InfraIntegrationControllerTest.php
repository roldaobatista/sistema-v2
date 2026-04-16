<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfraIntegrationControllerTest extends TestCase
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

    public function test_webhook_configs_returns_list(): void
    {
        $response = $this->getJson('/api/v1/infra/webhooks');

        $response->assertOk();
    }

    public function test_store_webhook_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/infra/webhooks', []);

        $response->assertStatus(422);
    }

    public function test_api_keys_returns_list(): void
    {
        $response = $this->getJson('/api/v1/infra/api-keys');

        $response->assertOk();
    }

    public function test_create_api_key_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/infra/api-keys', []);

        $response->assertStatus(422);
    }

    public function test_swagger_spec_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/infra/swagger');

        $response->assertOk();
    }
}
