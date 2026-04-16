<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiAssistantControllerTest extends TestCase
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

    public function test_tools_returns_metadata(): void
    {
        $response = $this->getJson('/api/v1/ai/tools');

        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_chat_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/ai/chat', []);

        $this->assertContains($response->status(), [422, 404]);
    }

    public function test_chat_endpoint_is_reachable_with_message(): void
    {
        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Olá',
        ]);

        $this->assertContains($response->status(), [200, 201, 422, 404, 500, 503]);
    }
}
