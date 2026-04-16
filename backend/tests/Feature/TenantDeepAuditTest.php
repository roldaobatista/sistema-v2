<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tenant Module Deep Audit Tests — validates tenant CRUD, branch CRUD,
 * tenant settings, user invite/remove, dependency checks, and multi-tenant isolation.
 */
class TenantDeepAuditTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenantA = Tenant::factory()->create([
            'name' => 'Empresa A',
            'document' => '12345678000199',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Empresa B',
            'document' => '98765432000188',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

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

        $this->withoutMiddleware(CheckPermission::class);
        app()->instance('current_tenant_id', $this->tenantA->id);
    }

    // ══════════════════════════════════════════════
    // ── TENANT CRUD
    // ══════════════════════════════════════════════

    public function test_list_tenants_returns_paginated(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->getJson('/api/v1/tenants');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_create_tenant_with_valid_cnpj(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Nova Empresa',
            'document' => '11.222.333/0001-44',
            'email' => 'nova@empresa.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Nova Empresa');

        // Document should be stored without mask
        $newTenant = Tenant::where('name', 'Nova Empresa')->first();
        $this->assertEquals('11222333000144', $newTenant->document);
    }

    public function test_create_tenant_with_duplicate_document_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Duplicada',
            'document' => '12345678000199',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('document');
    }

    public function test_create_tenant_with_invalid_document_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Invalid',
            'document' => 'abc123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('document');
    }

    public function test_show_tenant_includes_full_address(): void
    {
        $this->tenantA->update([
            'address_street' => 'Rua das Flores',
            'address_number' => '100',
            'address_city' => 'Cuiabá',
            'address_state' => 'MT',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson("/api/v1/tenants/{$this->tenantA->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Empresa A');

        $this->assertNotNull($response->json('full_address'));
        $this->assertStringContainsString('Cuiabá', $response->json('full_address'));
    }

    public function test_update_tenant_strips_document_mask(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson("/api/v1/tenants/{$this->tenantA->id}", [
            'name' => 'Empresa A Atualizada',
            'document' => '99.888.777/0001-66',
        ]);

        $response->assertOk();
        $this->tenantA->refresh();
        $this->assertEquals('Empresa A Atualizada', $this->tenantA->name);
        $this->assertEquals('99888777000166', $this->tenantA->document);
    }

    public function test_delete_tenant_without_dependencies(): void
    {
        $emptyTenant = Tenant::factory()->create(['name' => 'Vazia']);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson("/api/v1/tenants/{$emptyTenant->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('tenants', ['id' => $emptyTenant->id]);
    }

    public function test_delete_tenant_with_users_returns_409(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->deleteJson("/api/v1/tenants/{$this->tenantA->id}");

        $response->assertStatus(409)
            ->assertJsonStructure(['dependencies']);

        $this->assertDatabaseHas('tenants', ['id' => $this->tenantA->id]);
    }

    public function test_tenant_stats_counts_correctly(): void
    {
        Tenant::factory()->create(['status' => Tenant::STATUS_TRIAL]);
        Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/tenants-stats');

        $response->assertOk()
            ->assertJsonStructure(['total', 'active', 'trial', 'inactive']);

        $this->assertGreaterThanOrEqual(2, $response->json('data.active'));
        $this->assertGreaterThanOrEqual(1, $response->json('trial'));
        $this->assertGreaterThanOrEqual(1, $response->json('inactive'));
    }

    // ══════════════════════════════════════════════
    // ── TENANT STATUS HELPERS
    // ══════════════════════════════════════════════

    public function test_tenant_status_helpers(): void
    {
        $active = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $inactive = Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);
        $trial = Tenant::factory()->create(['status' => Tenant::STATUS_TRIAL]);

        $this->assertTrue($active->isActive());
        $this->assertTrue($active->isAccessible());

        $this->assertTrue($inactive->isInactive());
        $this->assertFalse($inactive->isAccessible());

        $this->assertTrue($trial->isTrial());
        $this->assertTrue($trial->isAccessible());
    }

    // ══════════════════════════════════════════════
    // ── BRANCH CRUD
    // ══════════════════════════════════════════════

    public function test_create_branch(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/branches', [
            'name' => 'Filial Centro',
            'code' => 'FC01',
            'address_city' => 'Cuiabá',
            'address_state' => 'MT',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Filial Centro')
            ->assertJsonPath('data.code', 'FC01')
            ->assertJsonPath('tenant_id', $this->tenantA->id);
    }

    public function test_create_branch_with_duplicate_code_same_tenant_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $this->postJson('/api/v1/branches', ['name' => 'F1', 'code' => 'DUPL01'])->assertCreated();

        $response = $this->postJson('/api/v1/branches', ['name' => 'F2', 'code' => 'DUPL01']);
        $response->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_update_branch(): void
    {
        $branch = Branch::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Filial Original',
            'code' => 'F001',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson("/api/v1/branches/{$branch->id}", [
            'name' => 'Filial Atualizada',
        ]);

        $response->assertOk();
        $this->assertEquals('Filial Atualizada', $branch->fresh()->name);
    }

    public function test_delete_empty_branch(): void
    {
        $branch = Branch::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Filial Vazia',
            'code' => 'FVAZIA',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson("/api/v1/branches/{$branch->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_delete_branch_with_users_returns_409(): void
    {
        $branch = Branch::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Filial com Users',
            'code' => 'FUSER',
        ]);

        $this->regularUser->update(['branch_id' => $branch->id]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson("/api/v1/branches/{$branch->id}");

        $response->assertStatus(409)
            ->assertJsonStructure(['dependencies']);
    }

    // ══════════════════════════════════════════════
    // ── TENANT SETTINGS
    // ══════════════════════════════════════════════

    public function test_upsert_and_list_settings(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/tenant-settings', [
            'settings' => [
                ['key' => 'color_primary', 'value' => '#FF5500'],
                ['key' => 'timezone', 'value' => 'America/Cuiaba'],
            ],
        ]);

        $response->assertOk();
        $this->assertEquals('#FF5500', $response->json('color_primary'));
        $this->assertEquals('America/Cuiaba', $response->json('timezone'));

        // List should return same
        $listResponse = $this->getJson('/api/v1/tenant-settings');
        $listResponse->assertOk();
        $this->assertEquals('#FF5500', $listResponse->json('color_primary'));
    }

    public function test_show_single_setting(): void
    {
        TenantSetting::setValue($this->tenantA->id, 'test_key', 'test_value');

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/tenant-settings/test_key');

        $response->assertOk()
            ->assertJsonPath('key', 'test_key')
            ->assertJsonPath('value', 'test_value');
    }

    public function test_delete_setting(): void
    {
        TenantSetting::setValue($this->tenantA->id, 'to_delete', 'bye');

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson('/api/v1/tenant-settings/to_delete');

        $response->assertNoContent();

        // Confirm deleted
        $response2 = $this->getJson('/api/v1/tenant-settings/to_delete');
        $response2->assertOk();
        $this->assertNull($response2->json('value'));
    }

    public function test_delete_nonexistent_setting_returns_404(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson('/api/v1/tenant-settings/nonexistent_key_xyz');
        $response->assertNotFound();
    }

    // ══════════════════════════════════════════════
    // ── INVITE USER
    // ══════════════════════════════════════════════

    public function test_invite_new_user_creates_account(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson("/api/v1/tenants/{$this->tenantA->id}/invite", [
            'name' => 'Convidado Novo',
            'email' => 'convidado@test.com',
        ]);

        $response->assertCreated();
        $this->assertTrue($response->json('user') !== null);

        $newUser = User::where('email', 'convidado@test.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->tenants()->where('tenants.id', $this->tenantA->id)->exists());
    }

    public function test_invite_existing_user_links_to_tenant(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        // AdminB exists but not in tenantA
        $response = $this->postJson("/api/v1/tenants/{$this->tenantA->id}/invite", [
            'name' => 'Admin B',
            'email' => 'admin-b@test.com',
        ]);

        $response->assertCreated();
        $this->assertTrue($this->adminB->fresh()->tenants()->where('tenants.id', $this->tenantA->id)->exists());
    }

    public function test_invite_already_member_returns_422(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson("/api/v1/tenants/{$this->tenantA->id}/invite", [
            'name' => 'Regular',
            'email' => 'regular@test.com',
        ]);

        $response->assertStatus(422);
    }

    // ══════════════════════════════════════════════
    // ── REMOVE USER
    // ══════════════════════════════════════════════

    public function test_remove_user_from_tenant(): void
    {
        // Add a third user so tenant has at least 2 after removal
        $extraUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
        ]);
        $extraUser->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->deleteJson("/api/v1/tenants/{$this->tenantA->id}/users/{$this->regularUser->id}");

        $response->assertNoContent();
        $this->assertFalse($this->regularUser->fresh()->tenants()->where('tenants.id', $this->tenantA->id)->exists());
    }

    public function test_cannot_remove_self_from_tenant(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->deleteJson("/api/v1/tenants/{$this->tenantA->id}/users/{$this->adminA->id}");

        $response->assertStatus(422);
    }

    // ══════════════════════════════════════════════
    // ── AVAILABLE ROLES (tenant_id fix verification)
    // ══════════════════════════════════════════════

    public function test_available_roles_returns_tenant_and_global_roles(): void
    {
        Role::create(['name' => 'TenantRole', 'guard_name' => 'web', 'tenant_id' => $this->tenantA->id]);
        Role::create(['name' => 'GlobalRole', 'guard_name' => 'web', 'tenant_id' => null]);
        Role::create(['name' => 'OtherTenantRole', 'guard_name' => 'web', 'tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->getJson("/api/v1/tenants/{$this->tenantA->id}/roles");
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('TenantRole'));
        $this->assertTrue($names->contains('GlobalRole'));
        $this->assertFalse($names->contains('OtherTenantRole'));
    }
}
