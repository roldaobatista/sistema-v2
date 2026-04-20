<?php

namespace Tests\Feature\Api\V1;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ServiceOpsTenantContextTest extends TestCase
{
    public function test_sla_dashboard_requires_current_tenant_id_not_legacy_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => null,
        ]);
        $user->tenants()->attach($tenant->id, ['is_default' => true]);

        setPermissionsTeamId($tenant->id);
        Permission::findOrCreate('os.work_order.view');
        $user->givePermissionTo('os.work_order.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user)
            ->getJson('/api/v1/operational/service-ops/sla/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'Nenhuma empresa selecionada.');
    }
}
