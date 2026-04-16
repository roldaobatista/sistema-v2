<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Concerns\InteractsWithSchemaData;
use Illuminate\Database\Seeder;

class FleetReferenceSeeder extends Seeder
{
    use InteractsWithSchemaData;

    public function run(): void
    {
        $tenants = Tenant::query()->select('id')->orderBy('id')->get();
        if ($tenants->isEmpty()) {
            return;
        }

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
            ->values();

        $vehicleIds = $this->seedVehicles($tenantId, $userIds->all());
        if ($vehicleIds === []) {
            return;
        }

        $this->seedFuelLogs($tenantId, $vehicleIds, $userIds->all());
        $this->seedVehicleTires($tenantId, $vehicleIds);
        $this->seedPoolRequests($tenantId, $vehicleIds, $userIds->all());
        $this->seedAccidents($tenantId, $vehicleIds, $userIds->all());
    }

    private function seedVehicles(int $tenantId, array $userIds): array
    {
        if (! $this->tableExists('fleet_vehicles')) {
            return [];
        }

        $suffix = str_pad((string) $tenantId, 2, '0', STR_PAD_LEFT);
        $assigned = $userIds[0] ?? null;

        $catalog = [
            [
                'plate' => "K{$suffix}A1",
                'brand' => 'Fiat',
                'model' => 'Strada',
                'year' => 2023,
                'color' => 'Branco',
                'type' => 'car',
                'fuel_type' => 'flex',
                'odometer_km' => 28450,
                'status' => 'active',
                'notes' => 'Utilitario para atendimentos urbanos.',
            ],
            [
                'plate' => "K{$suffix}B2",
                'brand' => 'Volkswagen',
                'model' => 'Amarok',
                'year' => 2022,
                'color' => 'Prata',
                'type' => 'truck',
                'fuel_type' => 'diesel',
                'odometer_km' => 47210,
                'status' => 'active',
                'notes' => 'Viagens intermunicipais e cargas pesadas.',
            ],
            [
                'plate' => "K{$suffix}C3",
                'brand' => 'Renault',
                'model' => 'Kwid',
                'year' => 2024,
                'color' => 'Cinza',
                'type' => 'car',
                'fuel_type' => 'gasoline',
                'odometer_km' => 12990,
                'status' => 'maintenance',
                'notes' => 'Reserva tecnica e deslocamentos administrativos.',
            ],
            [
                'plate' => "K{$suffix}D4",
                'brand' => 'Honda',
                'model' => 'CG 160',
                'year' => 2023,
                'color' => 'Vermelho',
                'type' => 'motorcycle',
                'fuel_type' => 'gasoline',
                'odometer_km' => 19120,
                'status' => 'active',
                'notes' => 'Suporte para entregas rapidas e coleta de documentos.',
            ],
        ];

        $ids = [];
        foreach ($catalog as $row) {
            $vehicleId = $this->upsertAndGetId(
                'fleet_vehicles',
                [
                    'tenant_id' => $tenantId,
                    'plate' => $row['plate'],
                ],
                array_merge($row, [
                    'assigned_user_id' => $assigned,
                    'updated_at' => now(),
                ])
            );

            if ($vehicleId) {
                $ids[] = $vehicleId;
            }
        }

        return $ids;
    }

    private function seedFuelLogs(int $tenantId, array $vehicleIds, array $userIds): void
    {
        if (! $this->hasColumns('fuel_logs', ['tenant_id', 'fleet_vehicle_id', 'date'])) {
            return;
        }

        foreach (array_slice($vehicleIds, 0, 2) as $index => $vehicleId) {
            $driverId = $userIds[$index] ?? ($userIds[0] ?? null);
            $baseKm = 20000 + ($index * 5000);

            for ($i = 0; $i < 2; $i++) {
                $date = now()->subDays(15 - ($index * 3) - $i * 4)->toDateString();
                $liters = 42 + ($i * 3);
                $price = 5.79 + ($i * 0.18);
                $total = round($liters * $price, 2);

                $this->upsertRow(
                    'fuel_logs',
                    [
                        'tenant_id' => $tenantId,
                        'fleet_vehicle_id' => $vehicleId,
                        'date' => $date,
                    ],
                    [
                        'driver_id' => $driverId,
                        'odometer_km' => $baseKm + ($i * 680),
                        'liters' => $liters,
                        'price_per_liter' => $price,
                        'total_value' => $total,
                        'fuel_type' => $index === 1 ? 'diesel' : 'flex',
                        'gas_station' => $i === 0 ? 'Rede Posto Via Norte' : 'Posto Estrada Sul',
                        'consumption_km_l' => 14.7 - ($index * 1.9),
                        'receipt_path' => "fuel-receipts/tenant-{$tenantId}/vehicle-{$vehicleId}-{$date}.pdf",
                    ]
                );
            }
        }
    }

    private function seedVehicleTires(int $tenantId, array $vehicleIds): void
    {
        if (! $this->hasColumns('vehicle_tires', ['tenant_id', 'fleet_vehicle_id', 'position'])) {
            return;
        }

        $positions = ['E1', 'E2', 'D1', 'D2'];
        foreach (array_slice($vehicleIds, 0, 2) as $vehicleId) {
            foreach ($positions as $offset => $position) {
                $this->upsertRow(
                    'vehicle_tires',
                    [
                        'tenant_id' => $tenantId,
                        'fleet_vehicle_id' => $vehicleId,
                        'position' => $position,
                    ],
                    [
                        'serial_number' => "TIRE-{$tenantId}-{$vehicleId}-{$position}",
                        'brand' => $offset % 2 === 0 ? 'Pirelli' : 'Goodyear',
                        'model' => $offset % 2 === 0 ? 'Chrono' : 'Cargo Marathon',
                        'tread_depth' => 8.5 - ($offset * 0.7),
                        'retread_count' => $offset > 1 ? 1 : 0,
                        'installed_at' => now()->subMonths(4 + $offset)->toDateString(),
                        'installed_km' => 18000 + ($offset * 1200),
                        'status' => $offset === 3 ? 'warehouse' : 'active',
                    ]
                );
            }
        }
    }

    private function seedPoolRequests(int $tenantId, array $vehicleIds, array $userIds): void
    {
        if (! $this->hasColumns('vehicle_pool_requests', ['tenant_id', 'user_id', 'requested_start'])) {
            return;
        }

        if ($userIds === []) {
            return;
        }

        $requestDate = now()->addDays(2)->startOfDay();
        $this->upsertRow(
            'vehicle_pool_requests',
            [
                'tenant_id' => $tenantId,
                'user_id' => $userIds[0],
                'requested_start' => $requestDate,
            ],
            [
                'fleet_vehicle_id' => $vehicleIds[0] ?? null,
                'requested_end' => (clone $requestDate)->addHours(9),
                'actual_start' => null,
                'actual_end' => null,
                'purpose' => 'Visita tecnica em clientes com coleta de pesos padrao.',
                'status' => 'pending',
            ]
        );

        if (isset($vehicleIds[1], $userIds[1])) {
            $approvedStart = now()->subDays(3)->setTime(7, 30);
            $this->upsertRow(
                'vehicle_pool_requests',
                [
                    'tenant_id' => $tenantId,
                    'user_id' => $userIds[1],
                    'requested_start' => $approvedStart,
                ],
                [
                    'fleet_vehicle_id' => $vehicleIds[1],
                    'requested_end' => (clone $approvedStart)->addHours(8),
                    'actual_start' => (clone $approvedStart)->addMinutes(15),
                    'actual_end' => (clone $approvedStart)->addHours(8)->addMinutes(10),
                    'purpose' => 'Rota de manutencao preventiva em filiais.',
                    'status' => 'completed',
                ]
            );
        }
    }

    private function seedAccidents(int $tenantId, array $vehicleIds, array $userIds): void
    {
        if (! $this->hasColumns('vehicle_accidents', ['tenant_id', 'fleet_vehicle_id', 'occurrence_date'])) {
            return;
        }

        $vehicleId = $vehicleIds[0] ?? null;
        if (! $vehicleId) {
            return;
        }

        $this->upsertRow(
            'vehicle_accidents',
            [
                'tenant_id' => $tenantId,
                'fleet_vehicle_id' => $vehicleId,
                'occurrence_date' => now()->subDays(21)->toDateString(),
            ],
            [
                'driver_id' => $userIds[0] ?? null,
                'location' => 'Avenida Industrial, 1200 - Distrito Tecnico',
                'description' => 'Colisao leve em manobra de estacionamento com dano no para-choque traseiro.',
                'third_party_involved' => true,
                'third_party_info' => 'Veiculo terceiro: Fiat Toro, placa final 9051.',
                'police_report_number' => "BO-{$tenantId}-".now()->format('Ymd'),
                'photos' => [
                    "fleet/accidents/{$tenantId}/{$vehicleId}/foto-1.jpg",
                    "fleet/accidents/{$tenantId}/{$vehicleId}/foto-2.jpg",
                ],
                'estimated_cost' => 1850.00,
                'status' => 'insurance_claim',
            ]
        );
    }
}
