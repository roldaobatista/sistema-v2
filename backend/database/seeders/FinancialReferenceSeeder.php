<?php

namespace Database\Seeders;

use App\Models\AccountPayableCategory;
use App\Models\BankAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class FinancialReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $hasPayableCategories = Schema::hasTable('account_payable_categories');
        $hasBankAccounts = Schema::hasTable('bank_accounts');

        if (! $hasPayableCategories && ! $hasBankAccounts) {
            $this->command->warn('Tabelas financeiras de referencia nao encontradas. Seeder ignorado.');

            return;
        }

        $categories = [
            ['name' => 'Fornecedores - Insumos', 'color' => '#3b82f6', 'description' => 'Compras de insumos e materiais de consumo'],
            ['name' => 'Fornecedores - Pecas', 'color' => '#1d4ed8', 'description' => 'Compras de pecas para aplicacao em OS'],
            ['name' => 'Fretes e Logistica', 'color' => '#0ea5e9', 'description' => 'Fretes, transportadoras e envios'],
            ['name' => 'Energia e Utilidades', 'color' => '#f59e0b', 'description' => 'Energia, agua, internet e telefonia'],
            ['name' => 'Combustivel e Frota', 'color' => '#ef4444', 'description' => 'Combustivel, pedagio e despesas de frota'],
            ['name' => 'Manutencao de Veiculos', 'color' => '#dc2626', 'description' => 'Oficina, pneus e manutencao de frota'],
            ['name' => 'Servicos Terceirizados', 'color' => '#8b5cf6', 'description' => 'Prestadores e terceiros'],
            ['name' => 'Impostos e Taxas', 'color' => '#7c3aed', 'description' => 'Tributos, taxas e emolumentos'],
            ['name' => 'Folha e Beneficios', 'color' => '#14b8a6', 'description' => 'Folha, encargos e beneficios'],
            ['name' => 'Softwares e Assinaturas', 'color' => '#10b981', 'description' => 'Sistemas, licencas e assinaturas recorrentes'],
            ['name' => 'Despesas Bancarias', 'color' => '#334155', 'description' => 'Tarifas, juros e encargos bancarios'],
            ['name' => 'Aluguel e Condominio', 'color' => '#64748b', 'description' => 'Custos de ocupacao e estrutura'],
            ['name' => 'Treinamentos e Certificacoes', 'color' => '#6366f1', 'description' => 'Cursos, treinamentos e certificacoes'],
            ['name' => 'Marketing e Comercial', 'color' => '#ec4899', 'description' => 'Campanhas, anuncios e materiais comerciais'],
            ['name' => 'Outros Custos Operacionais', 'color' => '#6b7280', 'description' => 'Demais despesas operacionais'],
        ];

        $bankAccounts = [
            ['name' => 'Banco do Brasil - Operacional', 'bank_name' => 'Banco do Brasil', 'agency' => '1234-5', 'account_number' => '10234-8', 'account_type' => 'corrente', 'pix_key' => 'financeiro@empresa.com'],
            ['name' => 'Caixa Economica - Recebimentos', 'bank_name' => 'Caixa Economica Federal', 'agency' => '0102', 'account_number' => '445566-7', 'account_type' => 'corrente', 'pix_key' => '11222333000199'],
            ['name' => 'Bradesco - Folha de Pagamento', 'bank_name' => 'Bradesco', 'agency' => '7890', 'account_number' => '99887-1', 'account_type' => 'pagamento', 'pix_key' => null],
            ['name' => 'Santander - Reservas', 'bank_name' => 'Santander', 'agency' => '3321', 'account_number' => '55667-0', 'account_type' => 'poupanca', 'pix_key' => null],
            ['name' => 'Itau - Investimentos', 'bank_name' => 'Itau', 'agency' => '4501', 'account_number' => '77889-2', 'account_type' => 'corrente', 'pix_key' => 'investimentos@empresa.com'],
        ];

        $tenants = Tenant::query()->select('id')->get();

        foreach ($tenants as $tenant) {
            if ($hasPayableCategories) {
                foreach ($categories as $category) {
                    AccountPayableCategory::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'name' => $category['name'],
                        ],
                        [
                            'color' => $category['color'],
                            'description' => $category['description'],
                            'is_active' => true,
                        ]
                    );
                }
            }

            if ($hasBankAccounts) {
                $createdBy = User::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->orderBy('id')
                    ->value('id');

                if (! $createdBy) {
                    continue;
                }

                foreach ($bankAccounts as $account) {
                    BankAccount::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'name' => $account['name'],
                        ],
                        [
                            'bank_name' => $account['bank_name'],
                            'agency' => $account['agency'],
                            'account_number' => $account['account_number'],
                            'account_type' => $account['account_type'],
                            'pix_key' => $account['pix_key'],
                            'balance' => 0,
                            'is_active' => true,
                            'created_by' => $createdBy,
                        ]
                    );
                }
            }
        }

        $this->command->info('Referencias financeiras criadas/verificadas para '.$tenants->count().' tenant(s)');
    }
}
