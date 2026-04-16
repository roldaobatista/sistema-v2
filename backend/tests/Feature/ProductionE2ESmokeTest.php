<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionE2ESmokeTest extends TestCase
{
    public function test_authenticated_me_endpoint_smoke(): void
    {
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $user->tenants()->attach($tenant->id, ['is_default' => true]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk();
        $response->assertJsonPath('data.user.id', $user->id);
    }
}
