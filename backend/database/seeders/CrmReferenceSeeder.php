<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Concerns\InteractsWithSchemaData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmReferenceSeeder extends Seeder
{
    use InteractsWithSchemaData;

    public function run(): void
    {
        $tenants = Tenant::query()->select('id')->orderBy('id')->get();
        foreach ($tenants as $tenant) {
            $this->seedTenant((int) $tenant->id);
        }
    }

    private function seedTenant(int $tenantId): void
    {
        $userIds = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $customerIds = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->limit(30)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($customerIds === []) {
            return;
        }

        $pipelineMap = $this->loadPipelineStages($tenantId);
        if ($pipelineMap === []) {
            return;
        }

        $dealIds = $this->seedDeals($tenantId, $customerIds, $userIds, $pipelineMap);
        $formIds = $this->seedWebForms($tenantId, $userIds, $pipelineMap);
        $this->seedWebFormSubmissions($formIds, $customerIds, $dealIds);
        $this->seedReferrals($tenantId, $customerIds, $dealIds);
        $this->seedDealCompetitors($dealIds);
    }

    private function loadPipelineStages(int $tenantId): array
    {
        if (
            ! $this->hasColumns('crm_pipelines', ['id', 'tenant_id', 'slug']) ||
            ! $this->hasColumns('crm_pipeline_stages', ['id', 'pipeline_id'])
        ) {
            return [];
        }

        $hasFlags = $this->hasColumns('crm_pipeline_stages', ['is_won', 'is_lost']);
        $pipelines = DB::table('crm_pipelines')
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->get(['id', 'slug']);

        $map = [];
        foreach ($pipelines as $pipeline) {
            $stages = DB::table('crm_pipeline_stages')
                ->where('pipeline_id', $pipeline->id)
                ->orderBy('sort_order')
                ->get(['id', 'is_won', 'is_lost']);

            if ($stages->isEmpty()) {
                continue;
            }

            $defaultStage = $stages->first(function ($stage) use ($hasFlags) {
                if (! $hasFlags) {
                    return true;
                }

                return ! $stage->is_won && ! $stage->is_lost;
            }) ?? $stages->first();

            $wonStage = $hasFlags
                ? ($stages->first(fn ($stage) => (bool) $stage->is_won) ?? $defaultStage)
                : $defaultStage;

            $lostStage = $hasFlags
                ? ($stages->first(fn ($stage) => (bool) $stage->is_lost) ?? $defaultStage)
                : $defaultStage;

            $map[$pipeline->slug] = [
                'pipeline_id' => (int) $pipeline->id,
                'default_stage_id' => (int) $defaultStage->id,
                'won_stage_id' => (int) $wonStage->id,
                'lost_stage_id' => (int) $lostStage->id,
            ];
        }

        return $map;
    }

    private function seedDeals(int $tenantId, array $customerIds, array $userIds, array $pipelineMap): array
    {
        if (! $this->hasColumns('crm_deals', ['tenant_id', 'customer_id', 'pipeline_id', 'stage_id', 'title'])) {
            return [];
        }

        $definitions = [
            ['title' => 'Contrato anual de calibração industrial', 'status' => 'open', 'value' => 18500, 'probability' => 65, 'source' => 'prospeccao'],
            ['title' => 'Recalibração semestral laboratório farmacêutico', 'status' => 'won', 'value' => 9200, 'probability' => 100, 'source' => 'recompra'],
            ['title' => 'Upgrade de balança rodoviária e integração', 'status' => 'open', 'value' => 45600, 'probability' => 45, 'source' => 'indicacao'],
            ['title' => 'Pacote de manutenção preventiva multiunidade', 'status' => 'lost', 'value' => 13400, 'probability' => 0, 'source' => 'site'],
            ['title' => 'Contrato de SLA para 24 meses', 'status' => 'open', 'value' => 28750, 'probability' => 75, 'source' => 'parceria'],
            ['title' => 'Retomada comercial de cliente inativo', 'status' => 'won', 'value' => 7800, 'probability' => 100, 'source' => 'pos_venda'],
        ];

        $pipelineKeys = array_keys($pipelineMap);
        $dealIds = [];

        foreach ($definitions as $index => $definition) {
            $pipelineKey = $pipelineKeys[$index % count($pipelineKeys)];
            $pipeline = $pipelineMap[$pipelineKey];
            $status = $definition['status'];

            $stageId = $pipeline['default_stage_id'];
            if ($status === 'won') {
                $stageId = $pipeline['won_stage_id'];
            } elseif ($status === 'lost') {
                $stageId = $pipeline['lost_stage_id'];
            }

            $dealId = $this->upsertAndGetId(
                'crm_deals',
                [
                    'tenant_id' => $tenantId,
                    'title' => $definition['title'],
                ],
                [
                    'customer_id' => $customerIds[$index % count($customerIds)],
                    'pipeline_id' => $pipeline['pipeline_id'],
                    'stage_id' => $stageId,
                    'status' => $status,
                    'value' => $definition['value'],
                    'probability' => $definition['probability'],
                    'source' => $definition['source'],
                    'assigned_to' => $userIds[$index % max(count($userIds), 1)] ?? null,
                    'expected_close_date' => now()->addDays(10 + ($index * 7))->toDateString(),
                    'last_contact_at' => now()->subDays(2 + $index)->toDateString(),
                    'next_followup_at' => now()->addDays(1 + $index)->toDateString(),
                    'won_at' => $status === 'won' ? now()->subDays(5 + $index)->toDateString() : null,
                    'lost_at' => $status === 'lost' ? now()->subDays(3 + $index)->toDateString() : null,
                    'description' => 'Negócio gerado para cobrir fluxo completo de CRM.',
                    'notes' => 'Registro de referência para formulários e dashboards.',
                ]
            );

            if ($dealId) {
                $dealIds[] = $dealId;
            }
        }

        return $dealIds;
    }

    private function seedWebForms(int $tenantId, array $userIds, array $pipelineMap): array
    {
        if (! $this->hasColumns('crm_web_forms', ['tenant_id', 'name', 'slug', 'fields'])) {
            return [];
        }

        $pipelineIds = array_values(array_unique(array_map(fn ($item) => (int) $item['pipeline_id'], $pipelineMap)));
        $forms = [
            [
                'name' => 'Lead Site Institucional',
                'slug' => 'lead-site-institucional',
                'description' => 'Captação principal de oportunidades via site.',
                'fields' => [
                    ['name' => 'name', 'type' => 'text', 'label' => 'Nome', 'required' => true],
                    ['name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true],
                    ['name' => 'phone', 'type' => 'phone', 'label' => 'Telefone', 'required' => false],
                    ['name' => 'company', 'type' => 'text', 'label' => 'Empresa', 'required' => false],
                    ['name' => 'message', 'type' => 'textarea', 'label' => 'Necessidade', 'required' => false],
                ],
            ],
            [
                'name' => 'Campanha Recalibração',
                'slug' => 'campanha-recalibracao',
                'description' => 'Formulário focado em clientes com equipamentos próximos do vencimento.',
                'fields' => [
                    ['name' => 'name', 'type' => 'text', 'label' => 'Responsável', 'required' => true],
                    ['name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true],
                    ['name' => 'phone', 'type' => 'phone', 'label' => 'Telefone', 'required' => true],
                    ['name' => 'equipment_count', 'type' => 'number', 'label' => 'Quantidade de equipamentos', 'required' => false],
                ],
            ],
            [
                'name' => 'Landing Page SLA',
                'slug' => 'landing-page-sla',
                'description' => 'Cadastro rápido para contratos de SLA e manutenção contínua.',
                'fields' => [
                    ['name' => 'name', 'type' => 'text', 'label' => 'Nome', 'required' => true],
                    ['name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true],
                    ['name' => 'phone', 'type' => 'phone', 'label' => 'Telefone', 'required' => false],
                    ['name' => 'segment', 'type' => 'select', 'label' => 'Segmento', 'required' => false],
                ],
            ],
        ];

        $formIds = [];
        foreach ($forms as $index => $form) {
            $formId = $this->upsertAndGetId(
                'crm_web_forms',
                [
                    'tenant_id' => $tenantId,
                    'slug' => $form['slug'],
                ],
                [
                    'name' => $form['name'],
                    'description' => $form['description'],
                    'fields' => $form['fields'],
                    'pipeline_id' => $pipelineIds[$index % max(count($pipelineIds), 1)] ?? null,
                    'assign_to' => $userIds[$index % max(count($userIds), 1)] ?? null,
                    'sequence_id' => null,
                    'redirect_url' => 'https://app.kalibrium.local/obrigado',
                    'success_message' => 'Recebemos seus dados. Nossa equipe retornará em breve.',
                    'is_active' => true,
                    'submissions_count' => 4 + $index,
                ]
            );

            if ($formId) {
                $formIds[] = $formId;
            }
        }

        return $formIds;
    }

    private function seedWebFormSubmissions(array $formIds, array $customerIds, array $dealIds): void
    {
        if ($formIds === [] || ! $this->hasColumns('crm_web_form_submissions', ['form_id', 'data'])) {
            return;
        }

        $baseRows = [
            ['name' => 'Contato Comercial Alpha', 'email' => 'alpha@lead.test', 'phone' => '(11) 91111-1111', 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'search-calibracao'],
            ['name' => 'Operações Beta', 'email' => 'beta@lead.test', 'phone' => '(11) 92222-2222', 'utm_source' => 'linkedin', 'utm_medium' => 'social', 'utm_campaign' => 'b2b-industria'],
            ['name' => 'Laboratório Gama', 'email' => 'gama@lead.test', 'phone' => '(11) 93333-3333', 'utm_source' => 'email', 'utm_medium' => 'newsletter', 'utm_campaign' => 'reativacao-base'],
        ];

        foreach ($formIds as $index => $formId) {
            $row = $baseRows[$index % count($baseRows)];
            $this->upsertRow(
                'crm_web_form_submissions',
                [
                    'form_id' => $formId,
                    'ip_address' => '203.0.113.'.(10 + $index),
                    'utm_campaign' => $row['utm_campaign'],
                ],
                [
                    'customer_id' => $customerIds[$index % count($customerIds)],
                    'deal_id' => $dealIds[$index % max(count($dealIds), 1)] ?? null,
                    'data' => $row,
                    'user_agent' => 'SeederBot/1.0',
                    'utm_source' => $row['utm_source'],
                    'utm_medium' => $row['utm_medium'],
                ]
            );
        }
    }

    private function seedReferrals(int $tenantId, array $customerIds, array $dealIds): void
    {
        if (! $this->hasColumns('crm_referrals', ['tenant_id', 'referrer_customer_id', 'referred_name'])) {
            return;
        }

        $rows = [
            ['referred_name' => 'Nova Indústria Delta', 'status' => 'pending', 'reward_type' => 'credit', 'reward_value' => 300, 'reward_given' => false],
            ['referred_name' => 'Distribuidora Ômega', 'status' => 'contacted', 'reward_type' => 'gift', 'reward_value' => 150, 'reward_given' => false],
            ['referred_name' => 'Laboratório Sigma', 'status' => 'converted', 'reward_type' => 'discount', 'reward_value' => 500, 'reward_given' => true],
            ['referred_name' => 'Moinho Aurora', 'status' => 'lost', 'reward_type' => null, 'reward_value' => null, 'reward_given' => false],
            ['referred_name' => 'Rede Mercado Sul', 'status' => 'converted', 'reward_type' => 'credit', 'reward_value' => 420, 'reward_given' => true],
        ];

        foreach ($rows as $index => $row) {
            $isConverted = $row['status'] === 'converted';
            $rewardGiven = (bool) $row['reward_given'];

            $this->upsertRow(
                'crm_referrals',
                [
                    'tenant_id' => $tenantId,
                    'referrer_customer_id' => $customerIds[$index % count($customerIds)],
                    'referred_name' => $row['referred_name'],
                ],
                [
                    'deal_id' => $isConverted ? ($dealIds[$index % max(count($dealIds), 1)] ?? null) : null,
                    'referred_email' => 'contato+'.($index + 1).'@indicacao.test',
                    'referred_phone' => '(11) 94'.str_pad((string) (1000 + $index), 4, '0', STR_PAD_LEFT).'-'.str_pad((string) (2000 + $index), 4, '0', STR_PAD_LEFT),
                    'status' => $row['status'],
                    'reward_type' => $row['reward_type'],
                    'reward_value' => $row['reward_value'],
                    'reward_given' => $rewardGiven,
                    'converted_at' => $isConverted ? now()->subDays(7 - $index)->toDateString() : null,
                    'reward_given_at' => $rewardGiven ? now()->subDays(3 + $index)->toDateString() : null,
                    'notes' => 'Indicacao semeada para cobrir fluxo de acompanhamento comercial.',
                ]
            );
        }
    }

    private function seedDealCompetitors(array $dealIds): void
    {
        if ($dealIds === [] || ! $this->hasColumns('crm_deal_competitors', ['deal_id', 'competitor_name'])) {
            return;
        }

        $rows = [
            ['competitor_name' => 'Metrica Prime', 'competitor_price' => 9100, 'outcome' => 'won'],
            ['competitor_name' => 'Precisao Técnica', 'competitor_price' => 13200, 'outcome' => 'lost'],
            ['competitor_name' => 'LabCert Solutions', 'competitor_price' => 7800, 'outcome' => 'won'],
            ['competitor_name' => 'CalibraMax', 'competitor_price' => 24500, 'outcome' => 'unknown'],
        ];

        foreach ($rows as $index => $row) {
            $this->upsertRow(
                'crm_deal_competitors',
                [
                    'deal_id' => $dealIds[$index % count($dealIds)],
                    'competitor_name' => $row['competitor_name'],
                ],
                [
                    'competitor_price' => $row['competitor_price'],
                    'strengths' => 'Proposta comercial agressiva e prazo curto.',
                    'weaknesses' => 'Suporte pós-venda limitado.',
                    'outcome' => $row['outcome'],
                ]
            );
        }
    }
}
