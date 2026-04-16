<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsappControllerTest extends TestCase
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

    public function test_get_config_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/whatsapp/config');

        $response->assertOk();
    }

    public function test_save_config_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/whatsapp/config', []);

        $response->assertStatus(422);
    }

    public function test_test_whatsapp_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/whatsapp/test', []);

        $response->assertStatus(422);
    }

    public function test_send_whatsapp_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/whatsapp/send', []);

        $response->assertStatus(422);
    }

    public function test_whatsapp_logs_returns_list(): void
    {
        $response = $this->getJson('/api/v1/whatsapp/logs');

        $response->assertOk();
    }
}
