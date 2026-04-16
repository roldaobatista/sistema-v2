<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * IAM Deep Audit Tests — validates role CRUD, permission matrix,
 * user management, tenant isolation, session management, and security boundaries.
 */
class IamDeepAuditTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    private User $regularUser;

    private Role $tenantRoleA;

    private Permission $permView;

    private Permission $permEdit;

    private PermissionGroup $permGroup;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenantA = Tenant::factory()->create(['name' => 'Empresa A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Empresa B']);

        $this->adminA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'email' => 'admin-a@test.com',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->adminA->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        $this->adminB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'email' => 'admin-b@test.com',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->adminB->tenants()->attach($this->tenantB->id, ['is_default' => true]);

        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'email' => 'regular@test.com',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->regularUser->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        // Create permission group
        $this->permGroup = PermissionGroup::create(['name' => 'Clientes', 'order' => 1]);
        $this->permView = Permission::create(['name' => 'customers.view', 'guard_name' => 'web', 'group_id' => $this->permGroup->id]);
        $this->permEdit = Permission::create(['name' => 'customers.edit', 'guard_name' => 'web', 'group_id' => $this->permGroup->id]);

        // Create tenant-scoped role
        $this->tenantRoleA = Role::create(['name' => 'Gerente', 'guard_name' => 'web', 'tenant_id' => $this->tenantA->id]);
        setPermissionsTeamId($this->tenantA->id);
        $this->tenantRoleA->givePermissionTo($this->permView);

        $this->withoutMiddleware(CheckPermission::class);
        app()->instance('current_tenant_id', $this->tenantA->id);
    }

    // ══════════════════════════════════════════════
    // ── ROLE CRUD
    // ══════════════════════════════════════════════

    public function test_create_role_returns_201(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/roles', [
            'name' => 'Vendedor',
            'display_name' => 'Vendedor Externo',
            'description' => 'Role para vendedores',
            'permissions' => [$this->permView->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Vendedor')
            ->assertJsonPath('data.display_name', 'Vendedor Externo')
            ->assertJsonPath('data.tenant_id', $this->tenantA->id);

        $this->assertDatabaseHas('roles', [
            'name' => 'Vendedor',
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    public function test_create_role_cannot_use_protected_names(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/roles', ['name' => 'super_admin', 'permissions' => []]);
        $response->assertStatus(422)->assertJsonValidationErrors('name');

        $response2 = $this->postJson('/api/v1/roles', ['name' => 'admin', 'permissions' => []]);
        $response2->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_create_duplicate_role_same_tenant_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $this->postJson('/api/v1/roles', ['name' => 'Operador', 'permissions' => []])->assertCreated();
        $response = $this->postJson('/api/v1/roles', ['name' => 'Operador', 'permissions' => []]);

        $response->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_create_same_role_name_different_tenants_succeeds(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $this->postJson('/api/v1/roles', ['name' => 'Manager', 'permissions' => []])->assertCreated();

        Sanctum::actingAs($this->adminB, ['*']);
        app()->instance('current_tenant_id', $this->tenantB->id);
        $this->postJson('/api/v1/roles', ['name' => 'Manager', 'permissions' => []])->assertCreated();
    }

    public function test_list_roles_shows_tenant_and_global_only(): void
    {
        Role::create(['name' => 'GlobalRole', 'guard_name' => 'web', 'tenant_id' => null]);
        Role::create(['name' => 'OtherTenantRole', 'guard_name' => 'web', 'tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->getJson('/api/v1/roles');
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Gerente'));
        $this->assertTrue($names->contains('GlobalRole'));
        $this->assertFalse($names->contains('OtherTenantRole'));
    }

    public function test_show_role_returns_with_permissions(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->getJson("/api/v1/roles/{$this->tenantRoleA->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Gerente')
            ->assertJsonStructure(['data' => ['permissions']]);
    }

    public function test_show_role_from_other_tenant_returns_404(): void
    {
        $otherRole = Role::create(['name' => 'Secret', 'guard_name' => 'web', 'tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson("/api/v1/roles/{$otherRole->id}");
        $response->assertNotFound();
    }

    public function test_update_role_changes_name_and_permissions(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson("/api/v1/roles/{$this->tenantRoleA->id}", [
            'name' => 'Coordenador',
            'permissions' => [$this->permView->id, $this->permEdit->id],
        ]);

        $response->assertOk();
        $this->tenantRoleA->refresh();
        $this->assertEquals('Coordenador', $this->tenantRoleA->name);
        $this->assertEquals(2, $this->tenantRoleA->permissions()->count());
    }

    public function test_update_global_role_is_forbidden(): void
    {
        $globalRole = Role::create(['name' => 'viewer', 'guard_name' => 'web', 'tenant_id' => null]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->putJson("/api/v1/roles/{$globalRole->id}", ['name' => 'hacked']);
        $response->assertForbidden();
    }

    public function test_delete_role_without_users_succeeds(): void
    {
        $emptyRole = Role::create(['name' => 'Temp', 'guard_name' => 'web', 'tenant_id' => $this->tenantA->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson("/api/v1/roles/{$emptyRole->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('roles', ['id' => $emptyRole->id]);
    }

    public function test_delete_role_with_users_fails(): void
    {
        $this->regularUser->assignRole($this->tenantRoleA);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson("/api/v1/roles/{$this->tenantRoleA->id}");

        $response->assertStatus(422);
        $this->assertStringContainsString('usuários atribuídos', $response->json('message'));
    }

    public function test_delete_protected_role_fails(): void
    {
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenantA->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson("/api/v1/roles/{$adminRole->id}");

        $response->assertStatus(422);
        $this->assertStringContainsString('sistema', $response->json('message'));
    }

    // ══════════════════════════════════════════════
    // ── ROLE CLONE
    // ══════════════════════════════════════════════

    public function test_clone_role_copies_permissions(): void
    {
        $this->tenantRoleA->givePermissionTo($this->permEdit);

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson("/api/v1/roles/{$this->tenantRoleA->id}/clone", [
            'name' => 'Gerente Junior',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Gerente Junior');

        $cloned = Role::where('name', 'Gerente Junior')->first();
        $this->assertNotNull($cloned);
        $this->assertEquals(
            $this->tenantRoleA->permissions->pluck('id')->sort()->values()->toArray(),
            $cloned->permissions->pluck('id')->sort()->values()->toArray()
        );
    }

    public function test_clone_role_from_other_tenant_forbidden(): void
    {
        $otherRole = Role::create(['name' => 'External', 'guard_name' => 'web', 'tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->postJson("/api/v1/roles/{$otherRole->id}/clone", ['name' => 'Stolen']);
        $response->assertForbidden();
    }

    // ══════════════════════════════════════════════
    // ── PERMISSION MATRIX
    // ══════════════════════════════════════════════

    public function test_permission_matrix_returns_correct_structure(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->getJson('/api/v1/permissions/matrix');

        $response->assertOk()
            ->assertJsonStructure([
                'roles' => [['name', 'display_name']],
                'matrix' => [['group', 'permissions']],
            ]);
    }

    public function test_toggle_permission_grants_and_revokes(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        // Grant
        $response = $this->postJson('/api/v1/permissions/toggle', [
            'role_id' => $this->tenantRoleA->id,
            'permission_id' => $this->permEdit->id,
        ]);
        $response->assertOk()->assertJsonPath('data.granted', true);

        // Revoke
        $response2 = $this->postJson('/api/v1/permissions/toggle', [
            'role_id' => $this->tenantRoleA->id,
            'permission_id' => $this->permEdit->id,
        ]);
        $response2->assertOk()->assertJsonPath('data.granted', false);
    }

    public function test_toggle_permission_on_global_role_forbidden(): void
    {
        $globalRole = Role::create(['name' => 'viewer', 'guard_name' => 'web', 'tenant_id' => null]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->postJson('/api/v1/permissions/toggle', [
            'role_id' => $globalRole->id,
            'permission_id' => $this->permView->id,
        ]);
        $response->assertForbidden();
    }

    // ══════════════════════════════════════════════
    // ── IAM USER CRUD
    // ══════════════════════════════════════════════

    public function test_create_user_in_tenant(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Novo Usuário',
            'email' => 'novo@test.com',
            'password' => 'Test1234!',
            'password_confirmation' => 'Test1234!',
            'roles' => [],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Novo Usuário');

        $newUser = User::where('email', 'novo@test.com')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals($this->tenantA->id, $newUser->current_tenant_id);
        $this->assertTrue($newUser->tenants()->where('tenants.id', $this->tenantA->id)->exists());
    }

    public function test_create_user_with_duplicate_email_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Clone',
            'email' => 'admin-a@test.com',
            'password' => 'Test1234!',
            'password_confirmation' => 'Test1234!',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_create_user_with_weak_password_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Weak',
            'email' => 'weak@test.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_list_users_scoped_to_tenant(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/users');

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('email');
        $this->assertTrue($emails->contains('admin-a@test.com'));
        $this->assertTrue($emails->contains('regular@test.com'));
        $this->assertFalse($emails->contains('admin-b@test.com'));
    }

    public function test_show_user_from_other_tenant_returns_404(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson("/api/v1/users/{$this->adminB->id}");
        $response->assertNotFound();
    }

    public function test_update_user_changes_name_and_role(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson("/api/v1/users/{$this->regularUser->id}", [
            'name' => 'Updated Name',
            'roles' => [$this->tenantRoleA->id],
        ]);

        $response->assertOk();
        $this->regularUser->refresh();
        $this->assertEquals('Updated Name', $this->regularUser->name);
        $this->assertTrue($this->regularUser->hasRole('Gerente'));
    }

    public function test_cannot_delete_self(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson("/api/v1/users/{$this->adminA->id}");
        $response->assertStatus(422);
        $this->assertStringContainsString('própria', $response->json('message'));
    }

    public function test_toggle_active_deactivates_and_revokes_tokens(): void
    {
        $this->regularUser->createToken('session-1');
        $this->regularUser->createToken('session-2');
        $this->assertGreaterThanOrEqual(2, $this->regularUser->tokens()->count());

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson("/api/v1/users/{$this->regularUser->id}/toggle-active");

        $response->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertFalse($this->regularUser->fresh()->is_active);
        $this->assertEquals(0, $this->regularUser->tokens()->count());
    }

    public function test_cannot_toggle_own_active_status(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->postJson("/api/v1/users/{$this->adminA->id}/toggle-active");
        $response->assertStatus(422);
    }

    // ══════════════════════════════════════════════
    // ── PASSWORD RESET (ADMIN)
    // ══════════════════════════════════════════════

    public function test_admin_reset_password_works(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson("/api/v1/users/{$this->regularUser->id}/reset-password", [
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);

        $response->assertOk();
        $this->regularUser->refresh();
        $this->assertTrue(Hash::check('NewPass1234!', $this->regularUser->password));
        $this->assertEquals(0, $this->regularUser->tokens()->count());
    }

    public function test_admin_reset_password_with_weak_password_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson("/api/v1/users/{$this->regularUser->id}/reset-password", [
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422);
    }

    // ══════════════════════════════════════════════
    // ── SESSION MANAGEMENT
    // ══════════════════════════════════════════════

    public function test_list_sessions_returns_tokens(): void
    {
        $this->regularUser->createToken('session-1');
        $this->regularUser->createToken('session-2');

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->getJson("/api/v1/users/{$this->regularUser->id}/sessions");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_revoke_session_deletes_specific_token(): void
    {
        $token1 = $this->regularUser->createToken('session-1');
        $this->regularUser->createToken('session-2');

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->deleteJson("/api/v1/users/{$this->regularUser->id}/sessions/{$token1->accessToken->id}");

        $response->assertOk();
        $this->assertEquals(1, $this->regularUser->tokens()->count());
    }

    public function test_force_logout_revokes_all_tokens(): void
    {
        $this->regularUser->createToken('session-1');
        $this->regularUser->createToken('session-2');
        $this->regularUser->createToken('session-3');

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson("/api/v1/users/{$this->regularUser->id}/force-logout");

        $response->assertOk();
        $this->assertStringContainsString('3', $response->json('message'));
        $this->assertEquals(0, $this->regularUser->tokens()->count());
    }

    public function test_cannot_force_logout_self(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->postJson("/api/v1/users/{$this->adminA->id}/force-logout");
        $response->assertStatus(422);
    }

    // ══════════════════════════════════════════════
    // ── BULK OPERATIONS
    // ══════════════════════════════════════════════

    public function test_bulk_toggle_active_deactivates_multiple_users(): void
    {
        $user2 = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'is_active' => true,
        ]);
        $user2->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/users/bulk-toggle-active', [
            'user_ids' => [$this->regularUser->id, $user2->id],
            'is_active' => false,
        ]);

        $response->assertOk()->assertJsonPath('affected', 2);
        $this->assertFalse($this->regularUser->fresh()->is_active);
        $this->assertFalse($user2->fresh()->is_active);
    }

    public function test_bulk_toggle_excludes_self(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/users/bulk-toggle-active', [
            'user_ids' => [$this->adminA->id, $this->regularUser->id],
            'is_active' => false,
        ]);

        $response->assertOk()->assertJsonPath('affected', 1);
        // Admin should NOT be affected
        $this->assertTrue($this->adminA->fresh()->is_active);
        // Regular user should be affected
        $this->assertFalse($this->regularUser->fresh()->is_active);
    }

    // ══════════════════════════════════════════════
    // ── AUDIT LOG
    // ══════════════════════════════════════════════

    public function test_role_creation_creates_audit_log(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $this->postJson('/api/v1/roles', [
            'name' => 'Audited',
            'permissions' => [],
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
        ]);
    }
}
