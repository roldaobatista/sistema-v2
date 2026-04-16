<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Concerns\InteractsWithSchemaData;
use Illuminate\Database\Seeder;

class HrReferenceSeeder extends Seeder
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

        $departmentIds = $this->seedOrganization($tenantId, $userIds);
        $positionIds = $this->seedPositions($tenantId, $departmentIds);
        $this->seedRecruitment($tenantId, $departmentIds, $positionIds, $userIds);
        $this->seedBenefits($tenantId, $userIds);
        $this->seedGeofences($tenantId);
        $this->seedJourneyRules($tenantId);
        $this->seedHolidays($tenantId);
        $this->seedVacationAndLeaves($tenantId, $userIds);
        $this->seedEmployeeDocuments($tenantId, $userIds);
        $this->seedOnboarding($tenantId, $userIds);
    }

    private function seedOrganization(int $tenantId, array $userIds): array
    {
        if (! $this->hasColumns('departments', ['tenant_id', 'name'])) {
            return [];
        }

        $managerId = $userIds[0] ?? null;
        $definitions = [
            ['name' => 'Diretoria Operacional', 'cost_center' => 'CC-100', 'manager_id' => $managerId, 'parent' => null],
            ['name' => 'Suporte Tecnico', 'cost_center' => 'CC-110', 'manager_id' => $userIds[1] ?? $managerId, 'parent' => 'Diretoria Operacional'],
            ['name' => 'Laboratorio de Calibracao', 'cost_center' => 'CC-120', 'manager_id' => $userIds[2] ?? $managerId, 'parent' => 'Diretoria Operacional'],
            ['name' => 'Financeiro e Controladoria', 'cost_center' => 'CC-200', 'manager_id' => $userIds[3] ?? $managerId, 'parent' => null],
            ['name' => 'Comercial e Relacionamento', 'cost_center' => 'CC-300', 'manager_id' => $userIds[4] ?? $managerId, 'parent' => null],
        ];

        $departmentIds = [];
        foreach ($definitions as $definition) {
            $payload = [
                'cost_center' => $definition['cost_center'],
                'manager_id' => $definition['manager_id'],
                'is_active' => true,
            ];

            $parentName = $definition['parent'];
            if ($parentName) {
                $payload['parent_id'] = $departmentIds[$parentName] ?? null;
            }

            $departmentId = $this->upsertAndGetId(
                'departments',
                ['tenant_id' => $tenantId, 'name' => $definition['name']],
                $payload
            );

            if ($departmentId) {
                $departmentIds[$definition['name']] = $departmentId;
            }
        }

        return $departmentIds;
    }

    private function seedPositions(int $tenantId, array $departmentIds): array
    {
        if ($departmentIds === [] || ! $this->hasColumns('positions', ['tenant_id', 'name', 'department_id'])) {
            return [];
        }

        $definitions = [
            ['name' => 'Coordenador Tecnico', 'department' => 'Suporte Tecnico', 'level' => 'manager'],
            ['name' => 'Tecnico de Campo Pleno', 'department' => 'Suporte Tecnico', 'level' => 'pleno'],
            ['name' => 'Analista Metrologista Senior', 'department' => 'Laboratorio de Calibracao', 'level' => 'senior'],
            ['name' => 'Assistente Financeiro', 'department' => 'Financeiro e Controladoria', 'level' => 'junior'],
            ['name' => 'Executivo de Contas', 'department' => 'Comercial e Relacionamento', 'level' => 'pleno'],
        ];

        $positionIds = [];
        foreach ($definitions as $definition) {
            $departmentId = $departmentIds[$definition['department']] ?? null;
            if (! $departmentId) {
                continue;
            }

            $positionId = $this->upsertAndGetId(
                'positions',
                [
                    'tenant_id' => $tenantId,
                    'department_id' => $departmentId,
                    'name' => $definition['name'],
                ],
                [
                    'level' => $definition['level'],
                    'description' => 'Cargo base para composição de equipe e recrutamento.',
                    'is_active' => true,
                ]
            );

            if ($positionId) {
                $positionIds[$definition['name']] = $positionId;
            }
        }

        return $positionIds;
    }

    private function seedRecruitment(int $tenantId, array $departmentIds, array $positionIds, array $userIds): void
    {
        if (! $this->hasColumns('job_postings', ['tenant_id', 'title', 'status'])) {
            return;
        }

        $openAt = now()->subDays(10)->toDateTimeString();
        $jobPostingId = $this->upsertAndGetId(
            'job_postings',
            [
                'tenant_id' => $tenantId,
                'title' => 'Tecnico de Campo - Pesagem Industrial',
            ],
            [
                'department_id' => $departmentIds['Suporte Tecnico'] ?? null,
                'position_id' => $positionIds['Tecnico de Campo Pleno'] ?? null,
                'description' => 'Atendimento em campo, manutenção e calibração de sistemas de pesagem.',
                'requirements' => 'Experiência em eletrônica básica, CNH B ativa e disponibilidade para viagens.',
                'salary_range_min' => 2800,
                'salary_range_max' => 4200,
                'status' => 'open',
                'opened_at' => $openAt,
            ]
        );

        if (! $jobPostingId || ! $this->hasColumns('candidates', ['tenant_id', 'job_posting_id', 'name', 'email'])) {
            return;
        }

        $candidates = [
            ['name' => 'Matheus Pereira', 'email' => 'matheus.pereira@curriculo.test', 'phone' => '(11) 98888-1201', 'stage' => 'screening', 'rating' => 4],
            ['name' => 'Aline Ribeiro', 'email' => 'aline.ribeiro@curriculo.test', 'phone' => '(11) 97777-1202', 'stage' => 'interview', 'rating' => 5],
            ['name' => 'Bruno Santos', 'email' => 'bruno.santos@curriculo.test', 'phone' => '(11) 96666-1203', 'stage' => 'technical_test', 'rating' => 3],
            ['name' => 'Karen Oliveira', 'email' => 'karen.oliveira@curriculo.test', 'phone' => '(11) 95555-1204', 'stage' => 'offer', 'rating' => 4],
        ];

        foreach ($candidates as $candidate) {
            $this->upsertRow(
                'candidates',
                [
                    'tenant_id' => $tenantId,
                    'job_posting_id' => $jobPostingId,
                    'email' => $candidate['email'],
                ],
                array_merge($candidate, [
                    'notes' => 'Candidato gerado para cobrir fluxo do kanban de recrutamento.',
                    'rejected_reason' => null,
                ])
            );
        }
    }

    private function seedBenefits(int $tenantId, array $userIds): void
    {
        if ($userIds === [] || ! $this->hasColumns('employee_benefits', ['tenant_id', 'user_id', 'type', 'value', 'start_date'])) {
            return;
        }

        $startDate = now()->startOfMonth()->toDateString();
        $definitions = [
            ['user_id' => $userIds[0], 'type' => 'vr', 'provider' => 'Pluxee', 'value' => 850, 'employee_contribution' => 85],
            ['user_id' => $userIds[0], 'type' => 'health', 'provider' => 'Unimed Empresarial', 'value' => 620, 'employee_contribution' => 186],
            ['user_id' => $userIds[1] ?? $userIds[0], 'type' => 'vt', 'provider' => 'Bilhete Unico', 'value' => 320, 'employee_contribution' => 32],
            ['user_id' => $userIds[2] ?? $userIds[0], 'type' => 'life_insurance', 'provider' => 'Porto Seguro', 'value' => 48, 'employee_contribution' => 0],
        ];

        foreach ($definitions as $definition) {
            $this->upsertRow(
                'employee_benefits',
                [
                    'tenant_id' => $tenantId,
                    'user_id' => $definition['user_id'],
                    'type' => $definition['type'],
                    'start_date' => $startDate,
                ],
                array_merge($definition, [
                    'end_date' => null,
                    'is_active' => true,
                    'notes' => 'Benefício de referência para testes de RH.',
                ])
            );
        }
    }

    private function seedGeofences(int $tenantId): void
    {
        if (! $this->hasColumns('geofence_locations', ['tenant_id', 'name', 'latitude', 'longitude'])) {
            return;
        }

        $rows = [
            [
                'name' => 'Matriz Operacional',
                'latitude' => -23.55052,
                'longitude' => -46.63331,
                'radius_meters' => 300,
                'notes' => 'Base principal para controle de ponto do escritorio.',
            ],
            [
                'name' => 'Centro de Distribuicao',
                'latitude' => -23.49511,
                'longitude' => -46.52816,
                'radius_meters' => 250,
                'notes' => 'Geofence de entrada para equipe de estoque e expedicao.',
            ],
            [
                'name' => 'Cliente Estrategico - Zona Sul',
                'latitude' => -23.61277,
                'longitude' => -46.70122,
                'radius_meters' => 180,
                'notes' => 'Local recorrente de atendimento tecnico de alta prioridade.',
            ],
        ];

        foreach ($rows as $row) {
            $this->upsertRow(
                'geofence_locations',
                ['tenant_id' => $tenantId, 'name' => $row['name']],
                array_merge($row, ['is_active' => true])
            );
        }
    }

    private function seedJourneyRules(int $tenantId): void
    {
        if (! $this->hasColumns('journey_rules', ['tenant_id', 'name'])) {
            return;
        }

        $rows = [
            [
                'name' => 'CLT 44h Semanal',
                'daily_hours' => 8.00,
                'weekly_hours' => 44.00,
                'overtime_weekday_pct' => 50,
                'overtime_weekend_pct' => 100,
                'overtime_holiday_pct' => 100,
                'night_shift_pct' => 20,
                'night_start' => '22:00:00',
                'night_end' => '05:00:00',
                'uses_hour_bank' => true,
                'hour_bank_expiry_months' => 6,
                'is_default' => true,
            ],
            [
                'name' => 'Escala 12x36',
                'daily_hours' => 12.00,
                'weekly_hours' => 36.00,
                'overtime_weekday_pct' => 60,
                'overtime_weekend_pct' => 100,
                'overtime_holiday_pct' => 100,
                'night_shift_pct' => 25,
                'night_start' => '22:00:00',
                'night_end' => '05:00:00',
                'uses_hour_bank' => false,
                'hour_bank_expiry_months' => 12,
                'is_default' => false,
            ],
            [
                'name' => 'Equipe Externa Flexivel',
                'daily_hours' => 8.00,
                'weekly_hours' => 40.00,
                'overtime_weekday_pct' => 50,
                'overtime_weekend_pct' => 100,
                'overtime_holiday_pct' => 120,
                'night_shift_pct' => 20,
                'night_start' => '22:00:00',
                'night_end' => '05:00:00',
                'uses_hour_bank' => true,
                'hour_bank_expiry_months' => 4,
                'is_default' => false,
            ],
        ];

        foreach ($rows as $row) {
            $this->upsertRow(
                'journey_rules',
                ['tenant_id' => $tenantId, 'name' => $row['name']],
                $row
            );
        }
    }

    private function seedHolidays(int $tenantId): void
    {
        if (! $this->hasColumns('holidays', ['tenant_id', 'name', 'date'])) {
            return;
        }

        $year = now()->year;
        $rows = [
            ['name' => 'Confraternizacao Universal', 'date' => "{$year}-01-01", 'is_national' => true, 'is_recurring' => true],
            ['name' => 'Dia do Trabalhador', 'date' => "{$year}-05-01", 'is_national' => true, 'is_recurring' => true],
            ['name' => 'Independencia do Brasil', 'date' => "{$year}-09-07", 'is_national' => true, 'is_recurring' => true],
            ['name' => 'Nossa Senhora Aparecida', 'date' => "{$year}-10-12", 'is_national' => true, 'is_recurring' => true],
            ['name' => 'Finados', 'date' => "{$year}-11-02", 'is_national' => true, 'is_recurring' => true],
            ['name' => 'Natal', 'date' => "{$year}-12-25", 'is_national' => true, 'is_recurring' => true],
            ['name' => 'Aniversario da Cidade (Local)', 'date' => "{$year}-04-08", 'is_national' => false, 'is_recurring' => true],
        ];

        foreach ($rows as $row) {
            $this->upsertRow(
                'holidays',
                ['tenant_id' => $tenantId, 'date' => $row['date']],
                $row
            );
        }
    }

    private function seedVacationAndLeaves(int $tenantId, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        if ($this->hasColumns('vacation_balances', ['tenant_id', 'user_id', 'acquisition_start'])) {
            foreach (array_slice($userIds, 0, 3) as $offset => $userId) {
                $acquisitionStart = now()->subYear()->startOfYear()->addMonths($offset)->toDateString();
                $acquisitionEnd = now()->subYear()->endOfYear()->addMonths($offset)->toDateString();

                $this->upsertRow(
                    'vacation_balances',
                    [
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'acquisition_start' => $acquisitionStart,
                    ],
                    [
                        'acquisition_end' => $acquisitionEnd,
                        'total_days' => 30,
                        'taken_days' => $offset === 0 ? 10 : 0,
                        'sold_days' => $offset === 1 ? 5 : 0,
                        'deadline' => now()->addMonths(11 - ($offset * 2))->toDateString(),
                        'status' => $offset === 2 ? 'available' : 'partially_taken',
                    ]
                );
            }
        }

        if ($this->hasColumns('leave_requests', ['tenant_id', 'user_id', 'type', 'start_date'])) {
            $pendingStart = now()->addDays(14)->toDateString();
            $pendingEnd = now()->addDays(18)->toDateString();

            $this->upsertRow(
                'leave_requests',
                [
                    'tenant_id' => $tenantId,
                    'user_id' => $userIds[0],
                    'type' => 'vacation',
                    'start_date' => $pendingStart,
                ],
                [
                    'end_date' => $pendingEnd,
                    'days_count' => 5,
                    'reason' => 'Descanso programado do periodo aquisitivo.',
                    'status' => 'pending',
                ]
            );

            if (isset($userIds[1])) {
                $approvedStart = now()->subDays(20)->toDateString();
                $approvedEnd = now()->subDays(17)->toDateString();

                $this->upsertRow(
                    'leave_requests',
                    [
                        'tenant_id' => $tenantId,
                        'user_id' => $userIds[1],
                        'type' => 'medical',
                        'start_date' => $approvedStart,
                    ],
                    [
                        'end_date' => $approvedEnd,
                        'days_count' => 4,
                        'reason' => 'Afastamento medico com atestado digitalizado.',
                        'status' => 'approved',
                        'approved_by' => $userIds[0],
                        'approved_at' => now()->subDays(21),
                    ]
                );
            }
        }
    }

    private function seedEmployeeDocuments(int $tenantId, array $userIds): void
    {
        if ($userIds === [] || ! $this->hasColumns('employee_documents', ['tenant_id', 'user_id', 'category', 'name'])) {
            return;
        }

        $docs = [
            [
                'user_id' => $userIds[0],
                'category' => 'aso',
                'name' => 'ASO Admissional',
                'file_path' => "hr/documents/{$tenantId}/aso-admissional.pdf",
                'issuer' => 'Clinica Ocupacional Vida',
                'issued_date' => now()->subMonths(5)->toDateString(),
                'expiry_date' => now()->addMonths(7)->toDateString(),
                'status' => 'valid',
                'is_mandatory' => true,
            ],
            [
                'user_id' => $userIds[0],
                'category' => 'nr',
                'name' => 'NR-35 Trabalho em Altura',
                'file_path' => "hr/documents/{$tenantId}/nr35-certificado.pdf",
                'issuer' => 'Escola Tecnica Segurança Total',
                'issued_date' => now()->subMonths(10)->toDateString(),
                'expiry_date' => now()->addMonths(2)->toDateString(),
                'status' => 'expiring',
                'is_mandatory' => true,
            ],
        ];

        if (isset($userIds[1])) {
            $docs[] = [
                'user_id' => $userIds[1],
                'category' => 'license',
                'name' => 'CNH Categoria B',
                'file_path' => "hr/documents/{$tenantId}/cnh-categoria-b.pdf",
                'issuer' => 'DETRAN',
                'issued_date' => now()->subYears(2)->toDateString(),
                'expiry_date' => now()->addMonths(14)->toDateString(),
                'status' => 'valid',
                'is_mandatory' => true,
            ];
        }

        foreach ($docs as $doc) {
            $this->upsertRow(
                'employee_documents',
                [
                    'tenant_id' => $tenantId,
                    'user_id' => $doc['user_id'],
                    'name' => $doc['name'],
                ],
                array_merge($doc, [
                    'uploaded_by' => $userIds[0],
                    'notes' => 'Documento de referencia para testes dos fluxos de RH.',
                ])
            );
        }
    }

    private function seedOnboarding(int $tenantId, array $userIds): void
    {
        if (! $this->hasColumns('onboarding_templates', ['tenant_id', 'name', 'type'])) {
            return;
        }

        $admissionTemplateId = $this->upsertAndGetId(
            'onboarding_templates',
            [
                'tenant_id' => $tenantId,
                'name' => 'Onboarding Tecnico de Campo',
            ],
            [
                'type' => 'admission',
                'default_tasks' => [
                    ['title' => 'Entrega de uniforme e EPIs', 'description' => 'Registrar termo de entrega e tamanhos.'],
                    ['title' => 'Treinamento de seguranca', 'description' => 'Treinamento NR10/NR35 conforme funcao.'],
                    ['title' => 'Configuracao de ferramentas digitais', 'description' => 'E-mail, app mobile e acesso ao sistema.'],
                    ['title' => 'Apresentacao de rota e carteira de clientes', 'description' => 'Regras de atendimento e SLA.'],
                ],
                'is_active' => true,
            ]
        );

        $this->upsertRow(
            'onboarding_templates',
            [
                'tenant_id' => $tenantId,
                'name' => 'Offboarding Administrativo',
            ],
            [
                'type' => 'dismissal',
                'default_tasks' => [
                    ['title' => 'Recolher cracha e equipamentos', 'description' => 'Validar checklist de devolucao.'],
                    ['title' => 'Encerrar acessos digitais', 'description' => 'Bloquear e-mail, VPN e sistemas internos.'],
                    ['title' => 'Homologacao de desligamento', 'description' => 'Conferir documentos e assinatura final.'],
                ],
                'is_active' => true,
            ]
        );

        if (! isset($userIds[0], $admissionTemplateId) || ! $this->hasColumns('onboarding_checklists', ['tenant_id', 'user_id', 'onboarding_template_id'])) {
            return;
        }

        $checklistId = $this->upsertAndGetId(
            'onboarding_checklists',
            [
                'tenant_id' => $tenantId,
                'user_id' => $userIds[0],
                'onboarding_template_id' => $admissionTemplateId,
            ],
            [
                'started_at' => now()->subDays(6),
                'status' => 'in_progress',
            ]
        );

        if (! $checklistId || ! $this->hasColumns('onboarding_checklist_items', ['onboarding_checklist_id', 'title'])) {
            return;
        }

        $tasks = [
            'Entrega de uniforme e EPIs',
            'Treinamento de seguranca',
            'Configuracao de ferramentas digitais',
            'Apresentacao de rota e carteira de clientes',
        ];

        foreach ($tasks as $order => $title) {
            $isDone = $order < 2;

            $this->upsertRow(
                'onboarding_checklist_items',
                [
                    'tenant_id' => $tenantId,
                    'onboarding_checklist_id' => $checklistId,
                    'title' => $title,
                ],
                [
                    'description' => 'Item criado automaticamente como base para o formulario.',
                    'order' => $order,
                    'is_completed' => $isDone,
                    'completed_at' => $isDone ? now()->subDays(5 - $order) : null,
                    'completed_by' => $isDone ? $userIds[0] : null,
                ]
            );
        }
    }
}
