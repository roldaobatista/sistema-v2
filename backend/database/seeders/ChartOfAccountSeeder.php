<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            $this->command->warn('Tabela chart_of_accounts nao encontrada. Seeder ignorado.');

            return;
        }

        $accounts = [
            ['code' => '1', 'name' => 'ATIVO', 'type' => ChartOfAccount::TYPE_ASSET, 'parent_code' => null],
            ['code' => '1.1', 'name' => 'ATIVO CIRCULANTE', 'type' => ChartOfAccount::TYPE_ASSET, 'parent_code' => '1'],
            ['code' => '1.1.01', 'name' => 'CAIXA', 'type' => ChartOfAccount::TYPE_ASSET, 'parent_code' => '1.1'],
            ['code' => '1.1.02', 'name' => 'BANCOS', 'type' => ChartOfAccount::TYPE_ASSET, 'parent_code' => '1.1'],
            ['code' => '1.1.03', 'name' => 'CONTAS A RECEBER', 'type' => ChartOfAccount::TYPE_ASSET, 'parent_code' => '1.1'],
            ['code' => '1.1.04', 'name' => 'ESTOQUE DE PECAS', 'type' => ChartOfAccount::TYPE_ASSET, 'parent_code' => '1.1'],
            ['code' => '1.1.05', 'name' => 'ADIANTAMENTOS A TECNICOS', 'type' => ChartOfAccount::TYPE_ASSET, 'parent_code' => '1.1'],
            ['code' => '1.2', 'name' => 'ATIVO NAO CIRCULANTE', 'type' => ChartOfAccount::TYPE_ASSET, 'parent_code' => '1'],
            ['code' => '1.2.01', 'name' => 'IMOBILIZADO', 'type' => ChartOfAccount::TYPE_ASSET, 'parent_code' => '1.2'],

            ['code' => '2', 'name' => 'PASSIVO', 'type' => ChartOfAccount::TYPE_LIABILITY, 'parent_code' => null],
            ['code' => '2.1', 'name' => 'PASSIVO CIRCULANTE', 'type' => ChartOfAccount::TYPE_LIABILITY, 'parent_code' => '2'],
            ['code' => '2.1.01', 'name' => 'FORNECEDORES', 'type' => ChartOfAccount::TYPE_LIABILITY, 'parent_code' => '2.1'],
            ['code' => '2.1.02', 'name' => 'CONTAS A PAGAR', 'type' => ChartOfAccount::TYPE_LIABILITY, 'parent_code' => '2.1'],
            ['code' => '2.1.03', 'name' => 'OBRIGACOES TRABALHISTAS', 'type' => ChartOfAccount::TYPE_LIABILITY, 'parent_code' => '2.1'],
            ['code' => '2.1.04', 'name' => 'OBRIGACOES TRIBUTARIAS', 'type' => ChartOfAccount::TYPE_LIABILITY, 'parent_code' => '2.1'],

            ['code' => '3', 'name' => 'RECEITAS', 'type' => ChartOfAccount::TYPE_REVENUE, 'parent_code' => null],
            ['code' => '3.1', 'name' => 'RECEITA DE SERVICOS', 'type' => ChartOfAccount::TYPE_REVENUE, 'parent_code' => '3'],
            ['code' => '3.1.01', 'name' => 'CALIBRACAO', 'type' => ChartOfAccount::TYPE_REVENUE, 'parent_code' => '3.1'],
            ['code' => '3.1.02', 'name' => 'MANUTENCAO PREVENTIVA', 'type' => ChartOfAccount::TYPE_REVENUE, 'parent_code' => '3.1'],
            ['code' => '3.1.03', 'name' => 'MANUTENCAO CORRETIVA', 'type' => ChartOfAccount::TYPE_REVENUE, 'parent_code' => '3.1'],
            ['code' => '3.1.04', 'name' => 'VISITA TECNICA', 'type' => ChartOfAccount::TYPE_REVENUE, 'parent_code' => '3.1'],
            ['code' => '3.1.05', 'name' => 'DESLOCAMENTO TECNICO', 'type' => ChartOfAccount::TYPE_REVENUE, 'parent_code' => '3.1'],
            ['code' => '3.2', 'name' => 'RECEITA DE PRODUTOS', 'type' => ChartOfAccount::TYPE_REVENUE, 'parent_code' => '3'],
            ['code' => '3.2.01', 'name' => 'VENDA DE PECAS', 'type' => ChartOfAccount::TYPE_REVENUE, 'parent_code' => '3.2'],

            ['code' => '4', 'name' => 'DESPESAS', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => null],
            ['code' => '4.1', 'name' => 'DESPESAS OPERACIONAIS', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4'],
            ['code' => '4.1.01', 'name' => 'COMBUSTIVEL E PEDAGIO', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4.1'],
            ['code' => '4.1.02', 'name' => 'ALIMENTACAO E HOSPEDAGEM', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4.1'],
            ['code' => '4.1.03', 'name' => 'MATERIAL APLICADO EM OS', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4.1'],
            ['code' => '4.1.04', 'name' => 'FERRAMENTAS E EPIS', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4.1'],
            ['code' => '4.1.05', 'name' => 'MANUTENCAO DE FROTA', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4.1'],
            ['code' => '4.2', 'name' => 'DESPESAS ADMINISTRATIVAS', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4'],
            ['code' => '4.2.01', 'name' => 'TELEFONIA E INTERNET', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4.2'],
            ['code' => '4.2.02', 'name' => 'SOFTWARES E SISTEMAS', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4.2'],
            ['code' => '4.2.03', 'name' => 'DESPESAS BANCARIAS', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4.2'],
            ['code' => '4.2.04', 'name' => 'MATERIAL DE ESCRITORIO', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4.2'],
            ['code' => '4.2.05', 'name' => 'TREINAMENTO E CERTIFICACAO', 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '4.2'],
        ];

        $tenants = Tenant::query()->select('id')->get();

        foreach ($tenants as $tenant) {
            foreach ($accounts as $account) {
                ChartOfAccount::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'code' => $account['code'],
                    ],
                    [
                        'name' => $account['name'],
                        'type' => $account['type'],
                        'is_system' => true,
                        'is_active' => true,
                    ]
                );
            }

            foreach ($accounts as $account) {
                $child = ChartOfAccount::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('code', $account['code'])
                    ->first();

                if (! $child) {
                    continue;
                }

                $targetParentId = null;
                if (! empty($account['parent_code'])) {
                    $parent = ChartOfAccount::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('code', $account['parent_code'])
                        ->first();
                    $targetParentId = $parent?->id;
                }

                if ((int) ($child->parent_id ?? 0) !== (int) ($targetParentId ?? 0)) {
                    $child->parent_id = $targetParentId;
                    $child->save();
                }
            }
        }

        $this->command->info(count($accounts).' contas contabeis criadas/verificadas para '.$tenants->count().' tenant(s)');
    }
}
