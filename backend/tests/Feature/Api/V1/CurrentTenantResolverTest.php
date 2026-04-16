<?php

namespace Tests\Feature\Api\V1;

use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenantResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class CurrentTenantResolverTest extends TestCase
{
    public function test_resolver_fails_closed_without_current_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => null,
        ]);

        $this->expectException(AuthorizationException::class);

        CurrentTenantResolver::resolveForUser($user);
    }

    public function test_resolver_uses_current_tenant_context_before_user_attributes(): void
    {
        $tenant = Tenant::factory()->create();
        $currentTenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => null,
        ]);

        app()->instance('current_tenant_id', $currentTenant->id);

        $this->assertSame($currentTenant->id, CurrentTenantResolver::resolveForUser($user));
    }
}
