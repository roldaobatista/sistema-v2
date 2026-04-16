<?php

use App\Models\PermissionGroup;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $group = PermissionGroup::firstOrCreate(
            ['name' => 'Central'],
            ['order' => 99]
        );

        $permissions = [
            ['name' => 'agenda.view.self', 'criticality' => 'LOW'],
            ['name' => 'agenda.view.team', 'criticality' => 'LOW'],
            ['name' => 'agenda.view.company', 'criticality' => 'MED'],
            ['name' => 'agenda.create.task', 'criticality' => 'LOW'],
            ['name' => 'agenda.assign', 'criticality' => 'MED'],
            ['name' => 'agenda.close.self', 'criticality' => 'LOW'],
            ['name' => 'agenda.close.any', 'criticality' => 'HIGH'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['group_id' => $group->id, 'criticality' => $perm['criticality']]
            );
        }

        // Atribuir permissões básicas aos roles existentes
        $rolePermissions = [
            'super_admin' => ['agenda.view.self', 'agenda.view.team', 'agenda.view.company', 'agenda.create.task', 'agenda.assign', 'agenda.close.self', 'agenda.close.any'],
            'admin' => ['agenda.view.self', 'agenda.view.team', 'agenda.view.company', 'agenda.create.task', 'agenda.assign', 'agenda.close.self', 'agenda.close.any'],
            'gerente' => ['agenda.view.self', 'agenda.view.team', 'agenda.create.task', 'agenda.assign', 'agenda.close.self', 'agenda.close.any'],
            'tecnico' => ['agenda.view.self', 'agenda.create.task', 'agenda.close.self'],
            'atendente' => ['agenda.view.self', 'agenda.create.task', 'agenda.close.self'],
            'vendedor' => ['agenda.view.self', 'agenda.create.task', 'agenda.close.self'],
            'financeiro' => ['agenda.view.self', 'agenda.view.team', 'agenda.create.task', 'agenda.close.self'],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }
    }

    public function down(): void
    {
        Permission::where('name', 'LIKE', 'agenda.%')->delete();
    }
};
