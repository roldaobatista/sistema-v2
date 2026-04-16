<?php

namespace App\Jobs;

use App\Models\FleetVehicle;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FleetDocExpirationAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 60;

    private const ALERT_DAYS = [30, 15, 7, 0];

    public function __construct()
    {
        $this->queue = 'alerts';
    }

    public function handle(): void
    {
        $alertsSent = 0;

        $tenantIds = Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id');

        foreach ($tenantIds as $tenantId) {
            try {
                app()->instance('current_tenant_id', $tenantId);

                // Cache fleet managers per tenant to avoid N+1
                $fleetManagers = User::permission('fleet.vehicle.view')
                    ->where('tenant_id', $tenantId)
                    ->pluck('id');

                if ($fleetManagers->isEmpty()) {
                    continue;
                }

                $vehicles = FleetVehicle::query()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->get();

                foreach ($vehicles as $vehicle) {
                    $docs = $this->getExpiringDocuments($vehicle);

                    foreach ($docs as $doc) {
                        foreach ($fleetManagers as $managerId) {
                            Notification::notify(
                                $tenantId,
                                $managerId,
                                'fleet_doc_expiration',
                                'Documento de Veículo Vencendo',
                                [
                                    'message' => "Veículo '{$vehicle->plate}': {$doc['type']} ".($doc['days'] <= 0 ? 'VENCIDO' : "vence em {$doc['days']} dias").'.',
                                    'icon' => 'file-warning',
                                    'color' => $doc['days'] <= 0 ? 'danger' : ($doc['days'] <= 7 ? 'warning' : 'info'),
                                    'data' => ['vehicle_id' => $vehicle->id, 'doc_type' => $doc['type']],
                                ]
                            );
                        }
                        $alertsSent++;
                    }
                }
            } catch (\Throwable $e) {
                Log::error("FleetDocExpirationAlertJob: falha no tenant {$tenantId}", ['error' => $e->getMessage()]);
            }
        }

        Log::info("FleetDocExpirationAlertJob: {$alertsSent} alertas de documentos gerados.");
    }

    private function getExpiringDocuments(FleetVehicle $vehicle): array
    {
        $docs = [];
        $checkFields = [
            'crlv_expiry' => 'CRLV',
            'insurance_expiry' => 'Seguro',
        ];

        foreach ($checkFields as $field => $label) {
            $date = $vehicle->{$field} ?? null;
            if (! $date) {
                continue;
            }

            $daysUntil = Carbon::now()->startOfDay()->diffInDays(Carbon::parse($date)->startOfDay(), false);

            if (in_array((int) $daysUntil, self::ALERT_DAYS) || $daysUntil < 0) {
                $docs[] = ['type' => $label, 'days' => (int) $daysUntil];
            }
        }

        return $docs;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('FleetDocExpirationAlertJob failed permanently', ['error' => $e->getMessage()]);
    }
}
