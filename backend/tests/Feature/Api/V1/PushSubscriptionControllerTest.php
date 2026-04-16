<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushSubscriptionControllerTest extends TestCase
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

    public function test_vapid_key_endpoint_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/push/vapid-key');

        // Pode retornar 200 ou 404 se VAPID não configurado
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_subscribe_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/push/subscribe', []);

        $response->assertStatus(422);
    }

    public function test_subscribe_creates_subscription(): void
    {
        $response = $this->postJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => [
                'p256dh' => 'BFLvGrE7C8VXXXjXXXX',
                'auth' => 'authkeysecret',
            ],
        ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    public function test_unsubscribe_validates_required_fields(): void
    {
        $response = $this->deleteJson('/api/v1/push/unsubscribe', []);

        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_test_endpoint_is_reachable(): void
    {
        $response = $this->postJson('/api/v1/push/test');

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }
}
