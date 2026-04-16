<?php

use App\Models\PermissionGroup;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $group = PermissionGroup::where('name', 'Reports')->first();
        if (! $group) {
            return;
        }

        foreach (['view', 'export'] as $action) {
            Permission::firstOrCreate([
                'name' => "reports.technician_cash_report.{$action}",
                'guard_name' => 'web',
            ], [
                'group_id' => $group->id,
                'criticality' => 'LOW',
            ]);
        }

        $admin = Role::where('name', 'admin')->first();
        $manager = Role::where('name', 'gerente')->first();

        $permissions = [
            'reports.technician_cash_report.view',
            'reports.technician_cash_report.export',
        ];

        if ($admin) {
            $admin->givePermissionTo($permissions);
        }
        if ($manager) {
            $manager->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        foreach (['view', 'export'] as $action) {
            Permission::where('name', "reports.technician_cash_report.{$action}")->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
