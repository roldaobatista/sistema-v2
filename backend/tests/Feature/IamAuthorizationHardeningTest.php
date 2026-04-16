<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\UserPolicy;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IamAuthorizationHardeningTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        Permission::findOrCreate('iam.user.view', 'web');
        Permission::findOrCreate('iam.user.export', 'web');
        Permission::findOrCreate('iam.audit_log.view', 'web');
        Permission::findOrCreate('iam.audit_log.export', 'web');
    }

    public function test_user_policy_uses_bound_tenant_context_even_when_user_current_tenant_changed(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantB->id,
            'is_active' => true,
        ]);
        $user->tenants()->attach($this->tenantA->id, ['is_default' => true]);
        $user->tenants()->attach($this->tenantB->id, ['is_default' => false]);

        $target = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'is_active' => true,
        ]);
        $target->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenantA->id);
        setPermissionsTeamId($this->tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo('iam.user.view');

        $policy = new UserPolicy;

        $this->assertTrue($policy->view($user->fresh(), $target->fresh()));
    }

    public function test_user_with_view_permission_cannot_export_users_csv(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'is_active' => true,
        ]);
        $user->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenantA->id);
        setPermissionsTeamId($this->tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo('iam.user.view');

        Sanctum::actingAs($user, ['tenant:'.$this->tenantA->id]);

        $this
            ->withoutMiddleware([EnsureTenantScope::class])
            ->get('/api/v1/users/export')
            ->assertForbidden();
    }

    public function test_user_with_view_permission_cannot_export_audit_logs(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'is_active' => true,
        ]);
        $user->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenantA->id);
        setPermissionsTeamId($this->tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo('iam.audit_log.view');

        Sanctum::actingAs($user, ['tenant:'.$this->tenantA->id]);

        $this
            ->withoutMiddleware([EnsureTenantScope::class])
            ->postJson('/api/v1/audit-logs/export')
            ->assertForbidden();
    }
}
