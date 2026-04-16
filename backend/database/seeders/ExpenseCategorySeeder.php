<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Combustivel', 'color' => '#ef4444', 'budget_limit' => 12000, 'default_affects_net_value' => true, 'default_affects_technician_cash' => true],
            ['name' => 'Pedagio', 'color' => '#8b5cf6', 'budget_limit' => 2500, 'default_affects_net_value' => true, 'default_affects_technician_cash' => true],
            ['name' => 'Estacionamento', 'color' => '#a855f7', 'budget_limit' => 1200, 'default_affects_net_value' => true, 'default_affects_technician_cash' => true],
            ['name' => 'Alimentacao e Refeicao', 'color' => '#f97316', 'budget_limit' => 6500, 'default_affects_net_value' => false, 'default_affects_technician_cash' => true],
            ['name' => 'Hospedagem', 'color' => '#06b6d4', 'budget_limit' => 7000, 'default_affects_net_value' => false, 'default_affects_technician_cash' => true],
            ['name' => 'Frete e Envio', 'color' => '#0ea5e9', 'budget_limit' => 4000, 'default_affects_net_value' => true, 'default_affects_technician_cash' => false],
            ['name' => 'Pecas e Materiais', 'color' => '#3b82f6', 'budget_limit' => 25000, 'default_affects_net_value' => true, 'default_affects_technician_cash' => true],
            ['name' => 'Ferramentas', 'color' => '#84cc16', 'budget_limit' => 5000, 'default_affects_net_value' => false, 'default_affects_technician_cash' => true],
            ['name' => 'EPIs e Uniformes', 'color' => '#14b8a6', 'budget_limit' => 2200, 'default_affects_net_value' => false, 'default_affects_technician_cash' => false],
            ['name' => 'Manutencao de Veiculo', 'color' => '#eab308', 'budget_limit' => 10000, 'default_affects_net_value' => false, 'default_affects_technician_cash' => false],
            ['name' => 'Lavagem e Conservacao de Frota', 'color' => '#f59e0b', 'budget_limit' => 1500, 'default_affects_net_value' => false, 'default_affects_technician_cash' => false],
            ['name' => 'Telefonia e Internet', 'color' => '#22c55e', 'budget_limit' => 1800, 'default_affects_net_value' => false, 'default_affects_technician_cash' => false],
            ['name' => 'Softwares e Assinaturas', 'color' => '#10b981', 'budget_limit' => 3500, 'default_affects_net_value' => false, 'default_affects_technician_cash' => false],
            ['name' => 'Taxas Bancarias', 'color' => '#334155', 'budget_limit' => 900, 'default_affects_net_value' => false, 'default_affects_technician_cash' => false],
            ['name' => 'Material de Escritorio', 'color' => '#6b7280', 'budget_limit' => 1300, 'default_affects_net_value' => false, 'default_affects_technician_cash' => false],
            ['name' => 'Treinamentos e Certificacoes', 'color' => '#6366f1', 'budget_limit' => 4500, 'default_affects_net_value' => false, 'default_affects_technician_cash' => false],
            ['name' => 'Outros', 'color' => '#64748b', 'budget_limit' => null, 'default_affects_net_value' => false, 'default_affects_technician_cash' => false],
        ];

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Criando categorias sem tenant_id (fallback).');
            foreach ($categories as $category) {
                ExpenseCategory::withoutGlobalScopes()->updateOrCreate(
                    ['name' => $category['name'], 'tenant_id' => null],
                    array_merge($category, ['active' => true])
                );
            }

            return;
        }

        foreach ($tenants as $tenant) {
            foreach ($categories as $category) {
                ExpenseCategory::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $category['name']],
                    array_merge($category, ['active' => true])
                );
            }
        }

        $this->command->info(count($categories).' categorias de despesa criadas/verificadas para '.$tenants->count().' tenant(s)');
    }
}
