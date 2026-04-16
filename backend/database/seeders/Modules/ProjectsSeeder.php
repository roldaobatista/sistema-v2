<?php

namespace Database\Seeders\Modules;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class ProjectsSeeder extends Seeder
{
    public function run(): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $permissions = [
            'projects.project.view',
            'projects.project.create',
            'projects.project.update',
            'projects.project.delete',
            'projects.milestone.manage',
            'projects.milestone.complete',
            'projects.resource.manage',
            'projects.time_entry.create',
            'projects.time_entry.view',
            'projects.dashboard.view',
            'projects.invoice.generate',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        $roleMap = [
            'super_admin' => $permissions,
            'admin' => $permissions,
            'gerente' => $permissions,
            'coordenador' => [
                'projects.project.view',
                'projects.project.create',
                'projects.project.update',
                'projects.milestone.manage',
                'projects.milestone.complete',
                'projects.resource.manage',
                'projects.time_entry.create',
                'projects.time_entry.view',
                'projects.dashboard.view',
            ],
            'viewer' => [
                'projects.project.view',
                'projects.time_entry.view',
                'projects.dashboard.view',
            ],
        ];

        foreach ($roleMap as $roleName => $rolePermissions) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role) {
                $role->givePermissionTo($rolePermissions);
            }
        }
    }
}
