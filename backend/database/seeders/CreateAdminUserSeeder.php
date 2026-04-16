<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class CreateAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['document' => '00.000.000/0001-00'],
            [
                'name' => 'Empresa Principal',
                'email' => 'contato@empresa.local',
                'phone' => null,
                'status' => 'active',
            ]
        );

        Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MTZ'],
            [
                'name' => 'Matriz',
                'address_city' => null,
                'address_state' => null,
            ]
        );

        $role = Role::where('name', 'super_admin')->first();
        if (! $role) {
            $this->command->warn('Role super_admin not found. Run PermissionsSeeder first.');

            return;
        }

        $adminEmail = (string) env('SEED_ADMIN_EMAIL', 'admin@example.test');
        $adminPassword = (string) env('SEED_ADMIN_PASSWORD', Str::random(32));

        $user = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrador',
                'password' => $adminPassword,
                'is_active' => true,
                'tenant_id' => $tenant->id,
                'current_tenant_id' => $tenant->id,
            ]
        );
        $user->tenants()->syncWithoutDetaching([$tenant->id => ['is_default' => true]]);

        setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole($role);

        $this->command->info('Admin user: '.$adminEmail.' / configured SEED_ADMIN_PASSWORD or generated random password');
    }
}
