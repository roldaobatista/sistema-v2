<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DefaultWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            Warehouse::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'code' => 'CENTRAL',
                ],
                [
                    'name' => 'Depósito Central',
                    'type' => 'fixed',
                    'is_active' => true,
                ]
            );
        }
    }
}
