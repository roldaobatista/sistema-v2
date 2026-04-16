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

class FleetMaintenanceAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 60;

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
                    ->whereNotNull('next_maintenance')
                    ->get();

                foreach ($vehicles as $vehicle) {
                    $daysUntilMaintenance = Carbon::now()
                        ->startOfDay()
                        ->diffInDays(Carbon::parse($vehicle->next_maintenance)->startOfDay(), false);

                    if ($daysUntilMaintenance > 30) {
                        continue;
                    }

                    foreach ($fleetManagers as $managerId) {
                        Notification::notify(
                            $tenantId,
                            $managerId,
                            'fleet_maintenance_alert',
                            'Manutenção Preventiva Próxima',
                            [
                                'message' => $daysUntilMaintenance <= 0
                                    ? "Veículo '{$vehicle->plate}' ({$vehicle->model}) está com manutenção vencida."
                                    : "Veículo '{$vehicle->plate}' ({$vehicle->model}) precisa de manutenção em {$daysUntilMaintenance} dias.",
                                'icon' => 'wrench',
                                'color' => $daysUntilMaintenance <= 0 ? 'danger' : 'warning',
                                'data' => ['vehicle_id' => $vehicle->id],
                            ]
                        );
                    }
                    $alertsSent++;
                }
            } catch (\Throwable $e) {
                Log::error("FleetMaintenanceAlertJob: falha no tenant {$tenantId}", ['error' => $e->getMessage()]);
            }
        }

        Log::info("FleetMaintenanceAlertJob: {$alertsSent} alertas de manutenção gerados.");
    }

    public function failed(\Throwable $e): void
    {
        Log::error('FleetMaintenanceAlertJob failed permanently', ['error' => $e->getMessage()]);
    }
}
