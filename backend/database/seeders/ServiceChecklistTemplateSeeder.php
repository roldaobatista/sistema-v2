<?php

namespace Database\Seeders;

use App\Models\ServiceChecklist;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ServiceChecklistTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('service_checklists') || ! Schema::hasTable('service_checklist_items')) {
            $this->command->warn('Tabelas de service checklist nao encontradas. Seeder ignorado.');

            return;
        }

        $templates = [
            [
                'name' => 'Checklist - Atendimento Inicial em Campo',
                'description' => 'Checklist padrao para abertura tecnica no cliente.',
                'items' => [
                    ['description' => 'Cliente confirmado no local', 'type' => 'yes_no', 'is_required' => true],
                    ['description' => 'Equipamento identificado (marca/modelo/serie)', 'type' => 'text', 'is_required' => true],
                    ['description' => 'Foto geral do equipamento antes da intervencao', 'type' => 'photo', 'is_required' => true],
                    ['description' => 'Condicao visual inicial registrada', 'type' => 'text', 'is_required' => true],
                    ['description' => 'Risco de seguranca identificado', 'type' => 'yes_no', 'is_required' => true],
                ],
            ],
            [
                'name' => 'Checklist - Manutencao Preventiva',
                'description' => 'Padrao de preventiva para balancas comerciais e industriais.',
                'items' => [
                    ['description' => 'Limpeza interna e externa executada', 'type' => 'check', 'is_required' => true],
                    ['description' => 'Conectores e cabos revisados', 'type' => 'check', 'is_required' => true],
                    ['description' => 'Verificacao de nivelamento', 'type' => 'check', 'is_required' => true],
                    ['description' => 'Teste de repetitividade aprovado', 'type' => 'yes_no', 'is_required' => true],
                    ['description' => 'Observacoes tecnicas da preventiva', 'type' => 'text', 'is_required' => false],
                ],
            ],
            [
                'name' => 'Checklist - Manutencao Corretiva',
                'description' => 'Checklist de diagnostico e reparo corretivo.',
                'items' => [
                    ['description' => 'Falha relatada pelo cliente documentada', 'type' => 'text', 'is_required' => true],
                    ['description' => 'Diagnostico de causa raiz executado', 'type' => 'check', 'is_required' => true],
                    ['description' => 'Pecas substituidas registradas', 'type' => 'text', 'is_required' => true],
                    ['description' => 'Foto da peca avariada anexada', 'type' => 'photo', 'is_required' => false],
                    ['description' => 'Teste funcional final aprovado', 'type' => 'yes_no', 'is_required' => true],
                ],
            ],
            [
                'name' => 'Checklist - Calibracao Rastreavel',
                'description' => 'Passo a passo para calibracao rastreavel.',
                'items' => [
                    ['description' => 'Temperatura ambiente registrada (C)', 'type' => 'number', 'is_required' => true],
                    ['description' => 'Umidade relativa registrada (%)', 'type' => 'number', 'is_required' => true],
                    ['description' => 'Padroes utilizados identificados', 'type' => 'text', 'is_required' => true],
                    ['description' => 'Pontos de carga executados conforme plano', 'type' => 'check', 'is_required' => true],
                    ['description' => 'Resultado da calibracao aprovado', 'type' => 'yes_no', 'is_required' => true],
                ],
            ],
            [
                'name' => 'Checklist - Instalacao de Equipamento',
                'description' => 'Checklist para instalacao e comissionamento.',
                'items' => [
                    ['description' => 'Base e estrutura conferidas', 'type' => 'check', 'is_required' => true],
                    ['description' => 'Ligacao eletrica concluida', 'type' => 'check', 'is_required' => true],
                    ['description' => 'Configuracao inicial aplicada', 'type' => 'check', 'is_required' => true],
                    ['description' => 'Teste com carga conhecido executado', 'type' => 'yes_no', 'is_required' => true],
                    ['description' => 'Treinamento rapido ao operador realizado', 'type' => 'yes_no', 'is_required' => false],
                ],
            ],
            [
                'name' => 'Checklist - Entrega e Encerramento',
                'description' => 'Checklist para fechamento da ordem de servico.',
                'items' => [
                    ['description' => 'Cliente validou o funcionamento', 'type' => 'yes_no', 'is_required' => true],
                    ['description' => 'Relatorio tecnico revisado', 'type' => 'check', 'is_required' => true],
                    ['description' => 'Fotos finais anexadas', 'type' => 'photo', 'is_required' => false],
                    ['description' => 'Pendencias registradas (se houver)', 'type' => 'text', 'is_required' => false],
                    ['description' => 'Assinatura do responsavel obtida', 'type' => 'yes_no', 'is_required' => true],
                ],
            ],
            [
                'name' => 'Checklist - Garantia/Retorno',
                'description' => 'Checklist para atendimento em garantia.',
                'items' => [
                    ['description' => 'Numero da OS original informado', 'type' => 'text', 'is_required' => true],
                    ['description' => 'Falha recorrente confirmada', 'type' => 'yes_no', 'is_required' => true],
                    ['description' => 'Intervencao sem custo registrada', 'type' => 'yes_no', 'is_required' => true],
                    ['description' => 'Analise de reincidencia preenchida', 'type' => 'text', 'is_required' => false],
                ],
            ],
            [
                'name' => 'Checklist - Auditoria de Qualidade',
                'description' => 'Checklist para supervisao tecnica e qualidade.',
                'items' => [
                    ['description' => 'Procedimento tecnico seguido', 'type' => 'yes_no', 'is_required' => true],
                    ['description' => 'Uso de EPI confirmado', 'type' => 'yes_no', 'is_required' => true],
                    ['description' => 'Tempo de atendimento dentro do SLA', 'type' => 'yes_no', 'is_required' => false],
                    ['description' => 'Nao conformidades registradas', 'type' => 'text', 'is_required' => false],
                ],
            ],
        ];

        $tenants = Tenant::query()->select('id')->get();

        foreach ($tenants as $tenant) {
            foreach ($templates as $template) {
                $checklist = ServiceChecklist::withoutGlobalScopes()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $template['name'],
                    ],
                    [
                        'description' => $template['description'],
                        'is_active' => true,
                    ]
                );

                if ($checklist->items()->exists()) {
                    continue;
                }

                foreach ($template['items'] as $index => $item) {
                    $checklist->items()->create([
                        'description' => $item['description'],
                        'type' => $item['type'],
                        'is_required' => $item['is_required'],
                        'order_index' => $index,
                    ]);
                }
            }
        }

        $this->command->info(count($templates).' checklists de servico criados/verificados para '.$tenants->count().' tenant(s)');
    }
}
