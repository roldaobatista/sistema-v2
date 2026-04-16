<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'technicians.cashbox.expense.create',
            'technicians.cashbox.expense.update',
            'technicians.cashbox.expense.delete',
            'technicians.cashbox.request_funds',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $roles = Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'technicians.cashbox.view'))
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = [
            'technicians.cashbox.expense.create',
            'technicians.cashbox.expense.update',
            'technicians.cashbox.expense.delete',
            'technicians.cashbox.request_funds',
        ];

        foreach ($permissions as $permissionName) {
            Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
