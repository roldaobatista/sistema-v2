<?php

namespace Tests\Feature\TenantIsolation;

use App\Http\Middleware\CheckPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Base class for tenant isolation tests.
 *
 * Provides two tenants (A and B) with authenticated users.
 * Middleware CheckPermission is disabled; Gate bypasses permissions.
 * EnsureTenantScope is kept active to test real tenant filtering.
 */
abstract class TenantIsolationTestCase extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $userA;

    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([
            CheckPermission::class,
        ]);

        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        $this->userA = User::factory()->create([
            'current_tenant_id' => $this->tenantA->id,
            'is_active' => true,
        ]);
        $this->userA->tenant_id = $this->tenantA->id;
        $this->userA->save();
        $this->userA->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        $this->userB = User::factory()->create([
            'current_tenant_id' => $this->tenantB->id,
            'is_active' => true,
        ]);
        $this->userB->tenant_id = $this->tenantB->id;
        $this->userB->save();
        $this->userB->tenants()->attach($this->tenantB->id, ['is_default' => true]);

        // Assign admin roles
        foreach ([[$this->userA, $this->tenantA], [$this->userB, $this->tenantB]] as [$user, $tenant]) {
            setPermissionsTeamId($tenant->id);
            app()->instance('current_tenant_id', $tenant->id);
            $user->assignRole('admin');
        }
    }

    /**
     * Act as tenant A user with proper tenant context.
     */
    protected function actingAsTenantA(): static
    {
        Sanctum::actingAs($this->userA, ['*']);
        app()->instance('current_tenant_id', $this->tenantA->id);

        return $this;
    }

    /**
     * Act as tenant B user with proper tenant context.
     */
    protected function actingAsTenantB(): static
    {
        Sanctum::actingAs($this->userB, ['*']);
        app()->instance('current_tenant_id', $this->tenantB->id);

        return $this;
    }

    /**
     * Create a resource for tenant B (for cross-tenant testing).
     */
    protected function createForTenantB(string $modelClass, array $attributes = [])
    {
        app()->instance('current_tenant_id', $this->tenantB->id);
        // BelongsToTenant trait auto-fills tenant_id via creating event
        // when current_tenant_id is set in the container
        $resource = $modelClass::factory()->create($attributes);
        app()->instance('current_tenant_id', $this->tenantA->id);

        return $resource;
    }
}
