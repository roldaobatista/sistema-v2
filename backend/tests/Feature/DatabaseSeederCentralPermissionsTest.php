<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class DatabaseSeederCentralPermissionsTest extends TestCase
{
    public function test_operational_roles_receive_central_permissions_on_seed(): void
    {
        $this->seed(DatabaseSeeder::class);

        setPermissionsTeamId(null);

        $manager = Role::where('name', 'gerente')->firstOrFail();
        $technician = Role::where('name', 'tecnico')->firstOrFail();
        $finance = Role::where('name', 'financeiro')->firstOrFail();

        $this->assertTrue($manager->hasPermissionTo('agenda.item.view'));
        $this->assertTrue($manager->hasPermissionTo('agenda.create.task'));
        $this->assertTrue($manager->hasPermissionTo('agenda.assign'));
        $this->assertTrue($manager->hasPermissionTo('agenda.manage.kpis'));
        $this->assertTrue($manager->hasPermissionTo('agenda.manage.rules'));

        $this->assertTrue($technician->hasPermissionTo('agenda.item.view'));
        $this->assertTrue($technician->hasPermissionTo('agenda.create.task'));
        $this->assertTrue($technician->hasPermissionTo('agenda.close.self'));

        $this->assertTrue($finance->hasPermissionTo('agenda.item.view'));
        $this->assertTrue($finance->hasPermissionTo('agenda.manage.kpis'));
        $this->assertTrue($finance->hasPermissionTo('agenda.manage.rules'));
    }
}
