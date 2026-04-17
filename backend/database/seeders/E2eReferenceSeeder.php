<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class E2eReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->createRestrictedTenantUser();

        Tenant::query()
            ->orderBy('id')
            ->each(function (Tenant $tenant): void {
                $customer = $this->customerFixtureFor($tenant);

                Customer::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'document' => $customer['document'],
                    ],
                    [
                        'type' => 'PJ',
                        'name' => $customer['name'],
                        'trade_name' => $customer['name'],
                        'email' => $customer['email'],
                        'phone' => '(65) 99999-0000',
                        'address_city' => 'Cuiaba',
                        'address_state' => 'MT',
                        'is_active' => true,
                    ]
                );
            });
    }

    /**
     * @return array{name: string, document: string, email: string}
     */
    private function customerFixtureFor(Tenant $tenant): array
    {
        $fixtures = [
            '12.345.678/0001-90' => [
                'name' => 'Cliente E2E Calibracoes Brasil',
                'document' => '00.000.000/0000-91',
                'email' => 'cliente.e2e+calibracoes@sistema.local',
            ],
            '98.765.432/0001-10' => [
                'name' => 'Cliente E2E TechAssist',
                'document' => '00.000.000/0000-92',
                'email' => 'cliente.e2e+techassist@sistema.local',
            ],
            '11.222.333/0001-44' => [
                'name' => 'Cliente E2E MedEquip',
                'document' => '00.000.000/0000-93',
                'email' => 'cliente.e2e+medequip@sistema.local',
            ],
        ];

        return $fixtures[$tenant->document] ?? [
            'name' => "Cliente E2E Tenant {$tenant->id}",
            'document' => sprintf('00.000.000/%04d-99', ((int) $tenant->id) % 10000),
            'email' => "cliente.e2e+tenant{$tenant->id}@sistema.local",
        ];
    }

    private function createRestrictedTenantUser(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['document' => '98.765.432/0001-10'],
            [
                'name' => 'TechAssist Serviços',
                'email' => 'contato@techassist.com.br',
                'phone' => '(21) 4000-0002',
                'status' => 'active',
            ]
        );

        Branch::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'SED'],
            [
                'name' => 'Sede Central',
                'address_city' => 'Campinas',
                'address_state' => 'SP',
            ]
        );

        $role = Role::where('name', 'tecnico')->first();
        if (! $role) {
            $this->command?->warn('Role tecnico not found. Run PermissionsSeeder first.');

            return;
        }

        $restrictedPassword = (string) (config('seeding.user_password') ?: 'CHANGE_ME_E2E_RESTRICTED_PASSWORD');

        $user = User::firstOrNew(['email' => 'ricardo@techassist.com.br']);
        $user->forceFill([
            'name' => 'Ricardo Técnico',
            'password' => $restrictedPassword,
            'is_active' => true,
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ])->save();
        $user->tenants()->syncWithoutDetaching([$tenant->id => ['is_default' => true]]);

        setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->syncRoles([$role->name]);
    }
}
