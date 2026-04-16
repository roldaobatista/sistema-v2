<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('payment_methods')) {
            $this->command->warn('Tabela payment_methods nao encontrada. Seeder ignorado.');

            return;
        }

        $methods = [
            ['code' => 'pix', 'name' => 'PIX', 'sort_order' => 10],
            ['code' => 'transferencia', 'name' => 'Transferencia Bancaria', 'sort_order' => 20],
            ['code' => 'boleto', 'name' => 'Boleto', 'sort_order' => 30],
            ['code' => 'cartao_credito', 'name' => 'Cartao de Credito', 'sort_order' => 40],
            ['code' => 'cartao_debito', 'name' => 'Cartao de Debito', 'sort_order' => 50],
            ['code' => 'dinheiro', 'name' => 'Dinheiro', 'sort_order' => 60],
            ['code' => 'corporate_card', 'name' => 'Cartao Corporativo', 'sort_order' => 70],
        ];

        $tenants = Tenant::query()->select('id')->get();

        foreach ($tenants as $tenant) {
            foreach ($methods as $method) {
                PaymentMethod::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'code' => $method['code'],
                    ],
                    [
                        'name' => $method['name'],
                        'is_active' => true,
                        'sort_order' => $method['sort_order'],
                    ]
                );
            }
        }

        $this->command->info(count($methods).' formas de pagamento criadas/verificadas para '.$tenants->count().' tenant(s)');
    }
}
