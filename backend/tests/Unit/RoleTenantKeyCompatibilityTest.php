<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Tests\TestCase;

class RoleTenantKeyCompatibilityTest extends TestCase
{
    public function test_query_create_with_tenant_id_persists_tenant_scope(): void
    {
        $tenant = Tenant::factory()->create();

        $role = Role::query()->create([
            'name' => 'tenant_scoped_role',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);

        $this->assertSame($tenant->id, (int) $role->tenant_id);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_query_create_rejects_legacy_team_id_attribute(): void
    {
        $tenant = Tenant::factory()->create();

        $this->expectException(MassAssignmentException::class);

        Role::query()->create([
            'name' => 'legacy_team_role',
            'guard_name' => 'web',
            'team_id' => $tenant->id,
        ]);
    }
}
