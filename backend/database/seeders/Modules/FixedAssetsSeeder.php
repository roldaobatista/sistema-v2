<?php

namespace Database\Seeders\Modules;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class FixedAssetsSeeder extends Seeder
{
    public function run(): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $permissions = [
            'fixed_assets.asset.view',
            'fixed_assets.asset.create',
            'fixed_assets.asset.update',
            'fixed_assets.asset.dispose',
            'fixed_assets.disposal.approve_high_value',
            'fixed_assets.depreciation.run',
            'fixed_assets.depreciation.view',
            'fixed_assets.dashboard.view',
            'fixed_assets.inventory.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        $roleMap = [
            'super_admin' => $permissions,
            'admin' => $permissions,
            'financeiro' => $permissions,
            'coordenador' => [
                'fixed_assets.asset.view',
                'fixed_assets.asset.create',
                'fixed_assets.asset.update',
                'fixed_assets.asset.dispose',
                'fixed_assets.depreciation.view',
                'fixed_assets.dashboard.view',
                'fixed_assets.inventory.manage',
            ],
            'viewer' => [
                'fixed_assets.asset.view',
                'fixed_assets.depreciation.view',
                'fixed_assets.dashboard.view',
            ],
        ];

        foreach ($roleMap as $roleName => $rolePermissions) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($rolePermissions);
            }
        }
    }
}
