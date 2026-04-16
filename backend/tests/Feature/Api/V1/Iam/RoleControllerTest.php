<?php

namespace Tests\Feature\Api\V1\Iam;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleControllerTest extends TestCase
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

    public function test_index_returns_roles_including_global_and_current_tenant(): void
    {
        // Role especifico deste tenant
        Role::create([
            'name' => 'custom_role_tenant',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/roles');

        // Controller pagina via Eloquent — estrutura Laravel
        $response->assertOk();

        $data = $response->json('data') ?? $response->json();
        $this->assertIsArray($data);
    }

    public function test_users_endpoint_returns_paginated_contract_with_positive_per_page_floor(): void
    {
        $role = Role::create([
            'name' => 'role_users_paginated',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);

        foreach (range(1, 3) as $index) {
            $member = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'current_tenant_id' => $this->tenant->id,
                'name' => "Role User {$index}",
            ]);
            $member->tenants()->attach($this->tenant->id, ['is_default' => true]);
            $member->assignRole($role);
        }

        $response = $this->getJson("/api/v1/roles/{$role->id}/users?per_page=0");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'email', 'is_active'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_show_returns_404_for_role_of_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignRole = Role::create([
            'name' => 'foreign_role_xyz',
            'guard_name' => 'web',
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->getJson("/api/v1/roles/{$foreignRole->id}");

        // abort_unless 404 quando role nao e global nem do tenant atual
        $response->assertStatus(404);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/v1/roles', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_protected_role_names(): void
    {
        // Nao pode criar roles com os nomes reservados super_admin/admin
        $responseSuper = $this->postJson('/api/v1/roles', ['name' => Role::SUPER_ADMIN]);
        $responseSuper->assertStatus(422)->assertJsonValidationErrors(['name']);

        $responseAdmin = $this->postJson('/api/v1/roles', ['name' => Role::ADMIN]);
        $responseAdmin->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_store_creates_role_with_display_name(): void
    {
        $response = $this->postJson('/api/v1/roles', [
            'name' => 'lote4_custom_role',
            'display_name' => 'Lote 4 Custom',
            'description' => 'Criado via teste',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('roles', [
            'name' => 'lote4_custom_role',
            'tenant_id' => $this->tenant->id,
            'display_name' => 'Lote 4 Custom',
        ]);
    }

    public function test_store_blocks_duplicate_role_name_in_same_tenant(): void
    {
        Role::create([
            'name' => 'duplicate_check',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/roles', [
            'name' => 'duplicate_check',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
