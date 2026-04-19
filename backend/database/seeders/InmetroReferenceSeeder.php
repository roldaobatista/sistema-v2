<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Database\Seeders\Concerns\InteractsWithSchemaData;
use Illuminate\Database\Seeder;

class InmetroReferenceSeeder extends Seeder
{
    use InteractsWithSchemaData;

    public function run(): void
    {
        if (! $this->tableExists('inmetro_owners')) {
            return;
        }

        $tenants = Tenant::query()->select('id')->orderBy('id')->get();
        foreach ($tenants as $tenant) {
            $this->seedTenant((int) $tenant->id);
        }
    }

    private function seedTenant(int $tenantId): void
    {
        $this->seedBaseConfig($tenantId);
        $competitorIds = $this->seedCompetitors($tenantId);
        $this->seedOwnersAndInstruments($tenantId, $competitorIds);
    }

    private function seedBaseConfig(int $tenantId): void
    {
        if (! $this->hasColumns('inmetro_base_configs', ['tenant_id'])) {
            return;
        }

        $this->upsertRow(
            'inmetro_base_configs',
            ['tenant_id' => $tenantId],
            [
                'base_lat' => -23.55052,
                'base_lng' => -46.63331,
                'base_address' => 'Av. Paulista, 1500 - Bela Vista',
                'base_city' => 'Sao Paulo',
                'base_state' => 'SP',
                'max_distance_km' => 320,
                'enrichment_sources' => ['xml_import', 'dados_gov', 'manual'],
                'last_enrichment_at' => now()->subDays(1),
            ]
        );
    }

    private function seedCompetitors(int $tenantId): array
    {
        if (! $this->hasColumns('inmetro_competitors', ['tenant_id', 'name', 'city', 'state'])) {
            return [];
        }

        $rows = [
            [
                'name' => 'Metrologia Centro Oeste',
                'cnpj' => "64.001.{$tenantId}01/0001-10",
                'authorization_number' => "IPEM-SP-{$tenantId}11",
                'phone' => '(11) 3222-1100',
                'email' => 'contato@mco.com.br',
                'address' => 'Rua das Oficinas, 120',
                'city' => 'Campinas',
                'state' => 'SP',
                'authorized_species' => ['balancas_comerciais', 'balancas_industriais'],
                'mechanics' => ['mecanica_geral', 'eletronica_embarcada'],
                'max_capacity' => '60000kg',
                'accuracy_classes' => ['III', 'IIII'],
                'authorization_valid_until' => now()->addMonths(14)->toDateString(),
                'total_repairs_done' => 128,
                'last_repair_date' => now()->subDays(8)->toDateString(),
                'website' => 'https://mco.exemplo.local',
            ],
            [
                'name' => 'BalanService Assistencia',
                'cnpj' => "64.002.{$tenantId}02/0001-20",
                'authorization_number' => "IPEM-SP-{$tenantId}22",
                'phone' => '(11) 3444-2200',
                'email' => 'suporte@balanservice.com.br',
                'address' => 'Av. Industrial, 880',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'authorized_species' => ['balancas_rodoviarias', 'balancas_analiticas'],
                'mechanics' => ['solda_estrutural', 'calibracao_rbc'],
                'max_capacity' => '120000kg',
                'accuracy_classes' => ['II', 'III', 'IIII'],
                'authorization_valid_until' => now()->addMonths(20)->toDateString(),
                'total_repairs_done' => 242,
                'last_repair_date' => now()->subDays(3)->toDateString(),
                'website' => 'https://balanservice.exemplo.local',
            ],
            [
                'name' => 'Peso Certo Manutencoes',
                'cnpj' => "64.003.{$tenantId}03/0001-30",
                'authorization_number' => "IPEM-SP-{$tenantId}33",
                'phone' => '(19) 3555-3300',
                'email' => 'vendas@pesocerto.com.br',
                'address' => 'Rua do Comercio, 455',
                'city' => 'Sorocaba',
                'state' => 'SP',
                'authorized_species' => ['balancas_comerciais'],
                'mechanics' => ['atendimento_campo', 'reparo_celula'],
                'max_capacity' => '30000kg',
                'accuracy_classes' => ['III'],
                'authorization_valid_until' => now()->addMonths(9)->toDateString(),
                'total_repairs_done' => 87,
                'last_repair_date' => now()->subDays(12)->toDateString(),
                'website' => 'https://pesocerto.exemplo.local',
            ],
        ];

        $ids = [];
        foreach ($rows as $row) {
            $id = $this->upsertAndGetId(
                'inmetro_competitors',
                [
                    'tenant_id' => $tenantId,
                    'name' => $row['name'],
                ],
                $row
            );

            if ($id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function seedOwnersAndInstruments(int $tenantId, array $competitorIds): void
    {
        $owners = [
            [
                'document' => "12.100.{$tenantId}01/0001-10",
                'name' => 'Supermercado Horizonte Ltda',
                'trade_name' => 'Horizonte Atacado',
                'type' => 'PJ',
                'phone' => '(11) 4001-1010',
                'email' => 'metrologia@horizonte.com.br',
                'lead_status' => 'negotiating',
                'priority' => 'high',
                'estimated_revenue' => 18500.00,
                'total_instruments' => 2,
                'location' => [
                    'address_street' => 'Rua Mercantil',
                    'address_number' => '120',
                    'address_neighborhood' => 'Distrito Comercial',
                    'address_city' => 'Sao Paulo',
                    'address_state' => 'SP',
                    'address_zip' => '01000-120',
                    'latitude' => -23.54430,
                    'longitude' => -46.63405,
                    'distance_from_base_km' => 8.4,
                ],
                'instruments' => [
                    [
                        'inmetro_number' => "SP-{$tenantId}-10011",
                        'brand' => 'Toledo',
                        'model' => 'Prix 5',
                        'capacity' => '30kg',
                        'instrument_type' => 'Balanca Comercial',
                        'current_status' => 'approved',
                        'last_verification_at' => now()->subMonths(11)->toDateString(),
                        'next_verification_at' => now()->addMonths(1)->toDateString(),
                        'last_executor' => 'IPEM-SP',
                    ],
                    [
                        'inmetro_number' => "SP-{$tenantId}-10012",
                        'brand' => 'Filizola',
                        'model' => 'BPW',
                        'capacity' => '300kg',
                        'instrument_type' => 'Balanca Plataforma',
                        'current_status' => 'rejected',
                        'last_verification_at' => now()->subMonths(13)->toDateString(),
                        'next_verification_at' => now()->subDays(20)->toDateString(),
                        'last_executor' => 'IPEM-SP',
                    ],
                ],
            ],
            [
                'document' => "12.200.{$tenantId}02/0001-20",
                'name' => 'Frigorifico Vale Forte',
                'trade_name' => 'Vale Forte Carnes',
                'type' => 'PJ',
                'phone' => '(19) 4002-2020',
                'email' => 'qualidade@valeforte.com.br',
                'lead_status' => 'contacted',
                'priority' => 'urgent',
                'estimated_revenue' => 27500.00,
                'total_instruments' => 2,
                'location' => [
                    'address_street' => 'Estrada do Abate',
                    'address_number' => '890',
                    'address_neighborhood' => 'Zona Industrial',
                    'address_city' => 'Campinas',
                    'address_state' => 'SP',
                    'address_zip' => '13000-890',
                    'latitude' => -22.90556,
                    'longitude' => -47.06083,
                    'distance_from_base_km' => 96.2,
                ],
                'instruments' => [
                    [
                        'inmetro_number' => "SP-{$tenantId}-20021",
                        'brand' => 'Micheletti',
                        'model' => 'MR 60T',
                        'capacity' => '60000kg',
                        'instrument_type' => 'Balanca Rodoviaria',
                        'current_status' => 'approved',
                        'last_verification_at' => now()->subMonths(10)->toDateString(),
                        'next_verification_at' => now()->addMonths(2)->toDateString(),
                        'last_executor' => 'IPEM-SP',
                    ],
                    [
                        'inmetro_number' => "SP-{$tenantId}-20022",
                        'brand' => 'Toledo',
                        'model' => '9094',
                        'capacity' => '1500kg',
                        'instrument_type' => 'Balanca Industrial',
                        'current_status' => 'repaired',
                        'last_verification_at' => now()->subMonths(2)->toDateString(),
                        'next_verification_at' => now()->addMonths(10)->toDateString(),
                        'last_executor' => 'BalanService Assistencia',
                    ],
                ],
            ],
        ];

        foreach ($owners as $ownerRow) {
            $ownerId = $this->upsertAndGetId(
                'inmetro_owners',
                [
                    'tenant_id' => $tenantId,
                    'document' => $ownerRow['document'],
                ],
                [
                    'name' => $ownerRow['name'],
                    'trade_name' => $ownerRow['trade_name'],
                    'type' => $ownerRow['type'],
                    'phone' => $ownerRow['phone'],
                    'email' => $ownerRow['email'],
                    'lead_status' => $ownerRow['lead_status'],
                    'priority' => $ownerRow['priority'],
                    'estimated_revenue' => $ownerRow['estimated_revenue'],
                    'total_instruments' => $ownerRow['total_instruments'],
                    'notes' => 'Lead seeded automaticamente para teste dos funis INMETRO.',
                ]
            );

            if (! $ownerId) {
                continue;
            }

            $locationData = $ownerRow['location'];
            $locationId = $this->upsertAndGetId(
                'inmetro_locations',
                [
                    'tenant_id' => $tenantId,
                    'owner_id' => $ownerId,
                    'address_city' => $locationData['address_city'],
                    'address_street' => $locationData['address_street'],
                ],
                array_merge($locationData, [
                    'tenant_id' => $tenantId,
                    'address_number' => $locationData['address_number'],
                    'state_registration' => "IE-{$tenantId}{$ownerId}",
                    'farm_name' => null,
                    'phone_local' => $ownerRow['phone'],
                    'email_local' => $ownerRow['email'],
                ])
            );

            if (! $locationId) {
                continue;
            }

            foreach ($ownerRow['instruments'] as $instrumentIndex => $instrumentRow) {
                $instrumentId = $this->upsertAndGetId(
                    'inmetro_instruments',
                    [
                        'location_id' => $locationId,
                        'inmetro_number' => $instrumentRow['inmetro_number'],
                    ],
                    array_merge($instrumentRow, [
                        'source' => 'seed_reference',
                    ])
                );

                if (! $instrumentId) {
                    continue;
                }

                $this->upsertRow(
                    'inmetro_history',
                    [
                        'instrument_id' => $instrumentId,
                        'event_type' => 'verification',
                        'event_date' => $instrumentRow['last_verification_at'],
                    ],
                    [
                        'result' => $instrumentRow['current_status'] === 'rejected' ? 'rejected' : 'approved',
                        'executor' => $instrumentRow['last_executor'],
                        'validity_date' => $instrumentRow['next_verification_at'],
                        'notes' => 'Historico inicial importado para compor dashboard.',
                        'source' => 'seed_reference',
                    ]
                );

                if ($instrumentRow['current_status'] === 'repaired') {
                    $this->upsertRow(
                        'inmetro_history',
                        [
                            'instrument_id' => $instrumentId,
                            'event_type' => 'repair',
                            'event_date' => now()->subMonths(3)->toDateString(),
                        ],
                        [
                            'result' => 'repaired',
                            'executor' => $instrumentRow['last_executor'],
                            'competitor_id' => $competitorIds[$instrumentIndex % max(count($competitorIds), 1)] ?? null,
                            'validity_date' => $instrumentRow['next_verification_at'],
                            'notes' => 'Reparo com ajuste de celula e nova lacracao.',
                            'source' => 'seed_reference',
                        ]
                    );
                }
            }
        }
    }
}
