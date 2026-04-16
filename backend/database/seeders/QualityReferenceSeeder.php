<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\Concerns\InteractsWithSchemaData;
use Illuminate\Database\Seeder;

class QualityReferenceSeeder extends Seeder
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
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $workOrderIds = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->limit(10)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $equipmentIds = Equipment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->limit(10)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $procedureIds = $this->seedProcedures($tenantId, $userIds);
        $complaintIds = $this->seedComplaints($tenantId, $userIds, $customerIds, $workOrderIds, $equipmentIds);
        $this->seedCorrectiveActions($tenantId, $userIds, $procedureIds, $complaintIds);
        $this->seedSurveys($tenantId, $customerIds, $workOrderIds);
        $this->seedAudits($tenantId, $userIds);
    }

    private function seedProcedures(int $tenantId, array $userIds): array
    {
        if (! $this->hasColumns('quality_procedures', ['tenant_id', 'code', 'title'])) {
            return [];
        }

        $approverId = $userIds[0] ?? null;
        $rows = [
            [
                'code' => 'PQ-001',
                'title' => 'Controle de Calibração de Equipamentos',
                'description' => 'Define periodicidade, rastreabilidade e tratamento de desvios de calibração.',
                'category' => 'calibration',
                'status' => 'active',
                'revision' => 3,
                'content' => 'Fluxo de calibração, emissão de certificados e aprovação técnica.',
                'next_review_date' => now()->addMonths(6)->toDateString(),
            ],
            [
                'code' => 'PQ-002',
                'title' => 'Atendimento Técnico em Campo',
                'description' => 'Padroniza checklist, evidências fotográficas e critérios de aceite do cliente.',
                'category' => 'operational',
                'status' => 'active',
                'revision' => 2,
                'content' => 'Sequência operacional para abertura, execução e encerramento de OS.',
                'next_review_date' => now()->addMonths(4)->toDateString(),
            ],
            [
                'code' => 'PQ-003',
                'title' => 'Tratativa de Reclamações',
                'description' => 'Classificação por severidade, SLA de resposta e fechamento com evidência.',
                'category' => 'management',
                'status' => 'draft',
                'revision' => 1,
                'content' => 'Fluxo de análise, resposta ao cliente e registro de ação corretiva.',
                'next_review_date' => now()->addMonths(3)->toDateString(),
            ],
            [
                'code' => 'PQ-004',
                'title' => 'Segurança em Intervenções Técnicas',
                'description' => 'Checklist mínimo de EPI e bloqueio de energias perigosas.',
                'category' => 'safety',
                'status' => 'active',
                'revision' => 4,
                'content' => 'Orientações de segurança aplicáveis a campo e laboratório.',
                'next_review_date' => now()->addMonths(8)->toDateString(),
            ],
        ];

        $ids = [];
        foreach ($rows as $row) {
            $id = $this->upsertAndGetId(
                'quality_procedures',
                ['tenant_id' => $tenantId, 'code' => $row['code']],
                array_merge($row, [
                    'approved_by' => $approverId,
                    'approved_at' => $row['status'] === 'active' ? now()->subDays(10)->toDateString() : null,
                ])
            );
            if ($id) {
                $ids[$row['code']] = $id;
            }
        }

        return $ids;
    }

    private function seedComplaints(int $tenantId, array $userIds, array $customerIds, array $workOrderIds, array $equipmentIds): array
    {
        if ($customerIds === [] || ! $this->hasColumns('customer_complaints', ['tenant_id', 'customer_id', 'description'])) {
            return [];
        }

        $assignedId = $userIds[0] ?? null;
        $rows = [
            [
                'description' => 'Cliente relatou divergência de leitura após manutenção preventiva.',
                'category' => 'service',
                'severity' => 'high',
                'status' => 'investigating',
                'resolution' => null,
                'response_due_at' => now()->addDays(2)->toDateString(),
                'responded_at' => now()->subHours(6),
            ],
            [
                'description' => 'Atraso na emissão de certificado para auditoria externa.',
                'category' => 'certificate',
                'severity' => 'medium',
                'status' => 'open',
                'resolution' => null,
                'response_due_at' => now()->addDay()->toDateString(),
                'responded_at' => null,
            ],
            [
                'description' => 'Cobrança indevida de deslocamento em visita coberta por contrato.',
                'category' => 'billing',
                'severity' => 'critical',
                'status' => 'resolved',
                'resolution' => 'Valor estornado e política de cobrança atualizada no cadastro do cliente.',
                'response_due_at' => now()->subDays(4)->toDateString(),
                'responded_at' => now()->subDays(4),
            ],
        ];

        $ids = [];
        foreach ($rows as $index => $row) {
            $resolved = $row['status'] === 'resolved';
            $id = $this->upsertAndGetId(
                'customer_complaints',
                [
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerIds[$index % count($customerIds)],
                    'description' => $row['description'],
                ],
                array_merge($row, [
                    'work_order_id' => $this->pickId($workOrderIds, $index),
                    'equipment_id' => $this->pickId($equipmentIds, $index),
                    'assigned_to' => $assignedId,
                    'resolved_at' => $resolved ? now()->subDays(3)->toDateString() : null,
                ])
            );

            if ($id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function seedCorrectiveActions(int $tenantId, array $userIds, array $procedureIds, array $complaintIds): void
    {
        if (! $this->hasColumns('corrective_actions', ['tenant_id', 'type', 'source', 'nonconformity_description'])) {
            return;
        }

        $responsibleId = $userIds[0] ?? null;
        $rows = [
            [
                'type' => 'corrective',
                'source' => 'complaint',
                'sourceable_type' => 'App\\Models\\CustomerComplaint',
                'sourceable_id' => $complaintIds[0] ?? null,
                'nonconformity_description' => 'Desvio de leitura identificado em equipamento após manutenção.',
                'root_cause' => 'Torque inadequado no ajuste final da célula de carga.',
                'action_plan' => 'Reforçar checklist de torque e dupla checagem em OS de manutenção.',
                'status' => 'in_progress',
                'deadline' => now()->addDays(7)->toDateString(),
            ],
            [
                'type' => 'preventive',
                'source' => 'internal',
                'sourceable_type' => 'App\\Models\\QualityProcedure',
                'sourceable_id' => $procedureIds['PQ-002'] ?? null,
                'nonconformity_description' => 'Risco de perda de evidência fotográfica em áreas com baixa conectividade.',
                'root_cause' => 'Dependência de envio online sem fila offline.',
                'action_plan' => 'Implantar política de sincronização automática e validação no encerramento.',
                'status' => 'open',
                'deadline' => now()->addDays(15)->toDateString(),
            ],
            [
                'type' => 'corrective',
                'source' => 'complaint',
                'sourceable_type' => 'App\\Models\\CustomerComplaint',
                'sourceable_id' => $complaintIds[2] ?? null,
                'nonconformity_description' => 'Faturamento aplicou deslocamento indevido em cliente contratual.',
                'root_cause' => 'Regra de cobrança não considerava exceções por tipo de contrato.',
                'action_plan' => 'Ajustar regra e treinar equipe financeira para revisão pré-fatura.',
                'status' => 'verified',
                'deadline' => now()->subDays(2)->toDateString(),
                'completed_at' => now()->subDays(3)->toDateString(),
                'verification_notes' => 'Auditoria interna confirmou ausência de recorrência após ajuste.',
            ],
        ];

        foreach ($rows as $row) {
            if (empty($row['sourceable_id'])) {
                continue;
            }

            $this->upsertRow(
                'corrective_actions',
                [
                    'tenant_id' => $tenantId,
                    'source' => $row['source'],
                    'nonconformity_description' => $row['nonconformity_description'],
                ],
                array_merge($row, [
                    'responsible_id' => $responsibleId,
                ])
            );
        }
    }

    private function seedSurveys(int $tenantId, array $customerIds, array $workOrderIds): void
    {
        if ($customerIds === []) {
            return;
        }

        if ($this->hasColumns('satisfaction_surveys', ['tenant_id', 'customer_id', 'channel'])) {
            $rows = [
                ['nps_score' => 10, 'service_rating' => 5, 'technician_rating' => 5, 'timeliness_rating' => 5, 'comment' => 'Atendimento excelente e rápido.', 'channel' => 'system'],
                ['nps_score' => 8, 'service_rating' => 4, 'technician_rating' => 5, 'timeliness_rating' => 4, 'comment' => 'Boa experiência geral, com pequeno atraso.', 'channel' => 'whatsapp'],
                ['nps_score' => 6, 'service_rating' => 3, 'technician_rating' => 4, 'timeliness_rating' => 2, 'comment' => 'Serviço resolvido, mas prazo acima do esperado.', 'channel' => 'email'],
                ['nps_score' => 9, 'service_rating' => 5, 'technician_rating' => 5, 'timeliness_rating' => 4, 'comment' => 'Equipe técnica muito preparada.', 'channel' => 'phone'],
            ];

            foreach ($rows as $index => $row) {
                $this->upsertRow(
                    'satisfaction_surveys',
                    [
                        'tenant_id' => $tenantId,
                        'customer_id' => $customerIds[$index % count($customerIds)],
                        'comment' => $row['comment'],
                    ],
                    array_merge($row, [
                        'work_order_id' => $this->pickId($workOrderIds, $index),
                    ])
                );
            }
        }

        if ($this->hasColumns('nps_surveys', ['tenant_id', 'customer_id', 'score'])) {
            $rows = [
                ['score' => 10, 'category' => 'promoter', 'comment' => 'Excelente suporte técnico e follow-up.', 'feedback' => 'Excelente suporte técnico e follow-up.'],
                ['score' => 7, 'category' => 'passive', 'comment' => 'Bom atendimento, precisa melhorar prazo.', 'feedback' => 'Bom atendimento, precisa melhorar prazo.'],
                ['score' => 4, 'category' => 'detractor', 'comment' => 'Demora no retorno da equipe.', 'feedback' => 'Demora no retorno da equipe.'],
            ];

            foreach ($rows as $index => $row) {
                $this->upsertRow(
                    'nps_surveys',
                    [
                        'tenant_id' => $tenantId,
                        'customer_id' => $customerIds[$index % count($customerIds)],
                        'score' => $row['score'],
                    ],
                    array_merge($row, [
                        'work_order_id' => $this->pickId($workOrderIds, $index),
                        'responded_at' => now()->subDays(2 + $index),
                    ])
                );
            }
        }
    }

    private function seedAudits(int $tenantId, array $userIds): void
    {
        if (! $this->hasColumns('quality_audits', ['tenant_id', 'audit_number', 'title', 'planned_date'])) {
            return;
        }

        $auditorId = $userIds[0] ?? null;
        $audits = [
            [
                'audit_number' => 'AUD-00001',
                'title' => 'Auditoria Interna - Processo de Calibração',
                'type' => 'internal',
                'scope' => 'Execução de calibração em laboratório e emissão de certificados.',
                'planned_date' => now()->addDays(5)->toDateString(),
                'status' => 'planned',
                'summary' => 'Auditoria programada para validação de aderência aos procedimentos PQ-001 e PQ-004.',
            ],
            [
                'audit_number' => 'AUD-00002',
                'title' => 'Auditoria de Fornecedor - Componentes Críticos',
                'type' => 'supplier',
                'scope' => 'Avaliação de fornecedor de célula de carga e prazos de entrega.',
                'planned_date' => now()->subDays(20)->toDateString(),
                'executed_date' => now()->subDays(18)->toDateString(),
                'status' => 'completed',
                'summary' => 'Fornecedor aprovado com uma observação sobre lead time.',
                'non_conformities_found' => 1,
                'observations_found' => 2,
            ],
        ];

        foreach ($audits as $audit) {
            $auditId = $this->upsertAndGetId(
                'quality_audits',
                ['tenant_id' => $tenantId, 'audit_number' => $audit['audit_number']],
                array_merge($audit, [
                    'auditor_id' => $auditorId,
                ])
            );

            if (! $auditId || ! $this->hasColumns('quality_audit_items', ['quality_audit_id', 'requirement', 'question'])) {
                continue;
            }

            $items = [
                [
                    'requirement' => 'Controle de documentos',
                    'clause' => '7.5',
                    'question' => 'Registros de calibração estão completos e rastreáveis?',
                    'result' => 'conform',
                    'evidence' => 'Checklist digital e certificados anexados.',
                    'notes' => 'Amostragem de 10 OS sem desvios.',
                ],
                [
                    'requirement' => 'Ações corretivas',
                    'clause' => '10.2',
                    'question' => 'Não conformidades anteriores tiveram encerramento eficaz?',
                    'result' => $audit['status'] === 'completed' ? 'non_conform' : null,
                    'evidence' => 'Plano CAPA com pendência de validação final.',
                    'notes' => 'Necessário reforçar critério de verificação.',
                ],
                [
                    'requirement' => 'Competência técnica',
                    'clause' => '7.2',
                    'question' => 'Equipe possui treinamentos obrigatórios vigentes?',
                    'result' => 'observation',
                    'evidence' => '2 certificados próximos do vencimento.',
                    'notes' => 'Programar reciclagem em 30 dias.',
                ],
            ];

            foreach ($items as $index => $item) {
                $this->upsertRow(
                    'quality_audit_items',
                    [
                        'quality_audit_id' => $auditId,
                        'question' => $item['question'],
                    ],
                    array_merge($item, [
                        'item_order' => $index,
                    ])
                );
            }
        }
    }

    private function pickId(array $ids, int $index): ?int
    {
        if ($ids === []) {
            return null;
        }

        return (int) $ids[$index % count($ids)];
    }
}
