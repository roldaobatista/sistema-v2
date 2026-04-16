<?php

namespace Tests\Feature\Api\V1\Iam;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
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

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_permissions_grouped(): void
    {
        // Cria algumas permissions para o tenant
        Permission::firstOrCreate(['name' => 'test.module.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'test.module.create', 'guard_name' => 'web']);

        $response = $this->getJson('/api/v1/permissions');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_matrix_endpoint_responds_successfully(): void
    {
        $response = $this->getJson('/api/v1/permissions/matrix');

        $response->assertOk();
    }

    public function test_toggle_rejects_missing_role_id(): void
    {
        $response = $this->postJson('/api/v1/permissions/toggle', [
            'permission_id' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_toggle_rejects_missing_permission_id(): void
    {
        $response = $this->postJson('/api/v1/permissions/toggle', [
            'role_id' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permission_id']);
    }

    public function test_toggle_rejects_nonexistent_role(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'test.toggle.scope',
            'guard_name' => 'web',
        ]);

        $response = $this->postJson('/api/v1/permissions/toggle', [
            'role_id' => 99999,
            'permission_id' => $permission->id,
        ]);

        // Pode retornar 422 (validacao exists) ou 404 (controller handle)
        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_toggle_blocks_editing_super_admin_role(): void
    {
        // AUDIT SECURITY: super_admin nunca pode ter permissoes alteradas via API.
        // Este teste garante que qualquer tentativa retorna 422 (nao 200).

        $superAdmin = Role::where('name', Role::SUPER_ADMIN)
            ->whereNull('tenant_id')
            ->first();

        $this->assertNotNull($superAdmin, 'Seeder deve garantir que super_admin existe');

        $permission = Permission::firstOrCreate([
            'name' => 'test.dangerous.permission',
            'guard_name' => 'web',
        ]);

        $response = $this->postJson('/api/v1/permissions/toggle', [
            'role_id' => $superAdmin->id,
            'permission_id' => $permission->id,
        ]);

        // Controller retorna 422 com mensagem "Permissoes do super_admin nao podem ser alteradas"
        // OU 403/404 (roles de sistema tem tenant_id null e retornam 403)
        $this->assertContains(
            $response->status(),
            [403, 404, 422],
            'super_admin foi modificado via API — PRIVILEGE ESCALATION P0'
        );
        $this->assertNotEquals(
            200,
            $response->status(),
            'Toggle em super_admin NAO pode retornar 200'
        );
    }

    public function test_toggle_blocks_editing_system_role_from_different_tenant(): void
    {
        // Uma role de OUTRO tenant nao pode ser editada pelo user atual.
        $otherTenant = Tenant::factory()->create();
        $foreignRole = Role::create([
            'name' => 'custom_foreign_role',
            'guard_name' => 'web',
            'tenant_id' => $otherTenant->id,
        ]);

        $permission = Permission::firstOrCreate([
            'name' => 'test.cross.tenant',
            'guard_name' => 'web',
        ]);

        $response = $this->postJson('/api/v1/permissions/toggle', [
            'role_id' => $foreignRole->id,
            'permission_id' => $permission->id,
        ]);

        // Deve ser 404 (Role nao encontrada neste tenant) — nunca 200
        $this->assertNotEquals(
            200,
            $response->status(),
            'Role de outro tenant foi editada — CROSS-TENANT ATTACK P0'
        );

        // Garantir que a role foreign mantem suas permissions originais
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertFalse(
            $foreignRole->fresh()->hasPermissionTo($permission),
            'Permission foi atribuida a role foreign via bypass cross-tenant'
        );
    }
}
