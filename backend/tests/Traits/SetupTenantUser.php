<?php

namespace Tests\Traits;

use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Centraliza setup de tenant + user + auth para Feature tests.
 * Reduz boilerplate repetido em ~160 arquivos.
 */
trait SetupTenantUser
{
    protected Tenant $tenant;

    protected User $user;

    protected function setUpTenantUser(array $userAttrs = [], array $tenantAttrs = []): void
    {
        $this->tenant = Tenant::factory()->create($tenantAttrs);
        $this->user = User::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ], $userAttrs));

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    protected function setUpTenantUserWithRole(string $role, array $permissions = []): void
    {
        $this->setUpTenantUser();

        // Spatie Teams mode requires team context before role assignment
        setPermissionsTeamId($this->tenant->id);

        $roleModel = Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);

        if (! empty($permissions)) {
            foreach ($permissions as $perm) {
                Permission::firstOrCreate([
                    'name' => $perm,
                    'guard_name' => 'web',
                ]);
            }
            $roleModel->syncPermissions($permissions);
        }

        $this->user->assignRole($roleModel);
    }

    protected function setUpTenantUserAdmin(): void
    {
        $this->setUpTenantUserWithRole('super_admin');
    }

    /**
     * Cria modelo do tenant atual (tenant_id preenchido).
     */
    protected function createTenantModel(string $modelClass, array $attrs = [])
    {
        return $modelClass::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
        ], $attrs));
    }
}
