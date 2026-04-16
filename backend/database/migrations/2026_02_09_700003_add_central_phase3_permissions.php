<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $newPermissions = [
            ['name' => 'agenda.item.view', 'criticality' => 'LOW'],
            ['name' => 'agenda.manage.kpis', 'criticality' => 'MED'],
            ['name' => 'agenda.manage.rules', 'criticality' => 'HIGH'],
        ];

        foreach ($newPermissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['criticality' => $perm['criticality']]
            );
        }

        $roleAssignments = [
            'super_admin' => ['agenda.item.view', 'agenda.manage.kpis', 'agenda.manage.rules'],
            'admin' => ['agenda.item.view', 'agenda.manage.kpis', 'agenda.manage.rules'],
            'gerente' => ['agenda.item.view', 'agenda.manage.kpis'],
            'tecnico' => ['agenda.item.view'],
            'atendente' => ['agenda.item.view'],
            'vendedor' => ['agenda.item.view'],
            'financeiro' => ['agenda.item.view', 'agenda.manage.kpis'],
        ];

        foreach ($roleAssignments as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'agenda.item.view',
            'agenda.manage.kpis',
            'agenda.manage.rules',
        ])->delete();
    }
};
