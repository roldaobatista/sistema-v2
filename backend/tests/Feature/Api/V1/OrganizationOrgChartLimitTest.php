<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrganizationOrgChartLimitTest extends TestCase
{
    public function test_org_chart_limits_department_payload(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->attach($tenant->id, ['is_default' => true]);

        setPermissionsTeamId($tenant->id);
        Permission::findOrCreate('hr.organization.view');
        $user->givePermissionTo('hr.organization.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Department::factory()
            ->count(201)
            ->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/hr/org-chart')
            ->assertOk()
            ->assertJsonCount(200, 'data');
    }
}
