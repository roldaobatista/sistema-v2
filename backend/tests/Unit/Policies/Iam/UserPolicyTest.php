<?php

namespace Tests\Unit\Policies\Iam;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Testes unitarios da UserPolicy.
 *
 * UserPolicy usa `modelBelongsToUserTenant()` que consulta a tabela pivot
 * `tenant_user` via `$model->tenants()->where('tenants.id', $tenantId)->exists()`.
 * Por isso NAO e possivel usar forceFill com tenant_id — e preciso ter users
 * reais persistidos com attach no pivot.
 */
class UserPolicyTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $admin;

    private User $noPerms;

    private User $targetUser;

    private User $foreignUser;

    /** @var array<string> */
    private array $permissions = [
        'iam.user.view',
        'iam.user.create',
        'iam.user.update',
        'iam.user.delete',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        foreach ($this->permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->setTenantContext($this->tenant->id);

        $adminRole = Role::findByName('admin', 'web');
        $adminRole->givePermissionTo($this->permissions);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->admin->assignRole('admin');

        $this->noPerms = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->noPerms->tenants()->attach($this->tenant->id, ['is_default' => true]);

        // Target user no MESMO tenant do admin (para testes de acesso)
        $this->targetUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->targetUser->tenants()->attach($this->tenant->id, ['is_default' => true]);

        // Foreign user em OUTRO tenant (para teste cross-tenant)
        $this->foreignUser = User::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'current_tenant_id' => $this->otherTenant->id,
        ]);
        $this->foreignUser->tenants()->attach($this->otherTenant->id, ['is_default' => true]);
    }

    public function test_user_policy_admin_has_full_access(): void
    {
        $policy = new UserPolicy;

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $this->targetUser));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $this->targetUser));
        $this->assertTrue($policy->delete($this->admin, $this->targetUser));
    }

    public function test_user_policy_no_permission_denies_all(): void
    {
        $policy = new UserPolicy;

        $this->assertFalse($policy->viewAny($this->noPerms));
        $this->assertFalse($policy->view($this->noPerms, $this->targetUser));
        $this->assertFalse($policy->create($this->noPerms));
        $this->assertFalse($policy->update($this->noPerms, $this->targetUser));
        $this->assertFalse($policy->delete($this->noPerms, $this->targetUser));
    }

    public function test_user_policy_cross_tenant_denies(): void
    {
        $policy = new UserPolicy;

        // Mesmo admin com todas as permissoes NAO pode ver/editar/deletar
        // usuario de outro tenant — pivot tenants() nao vai retornar o user.
        $this->assertFalse($policy->view($this->admin, $this->foreignUser));
        $this->assertFalse($policy->update($this->admin, $this->foreignUser));
        $this->assertFalse($policy->delete($this->admin, $this->foreignUser));
    }
}
