<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\PeripheralAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPeripheralAlerts extends Command
{
    protected $signature = 'alerts:process-peripheral {--tenant= : Process specific tenant only} {--days=30 : Alert threshold in days}';

    protected $description = 'Process automated alerts for Fleet, HR, and Quality modules across all tenants';

    public function handle(PeripheralAlertService $service): int
    {
        $alertDays = (int) $this->option('days');
        $service->setAlertDays($alertDays);

        $tenantId = $this->option('tenant');

        if ($tenantId) {
            app()->instance('current_tenant_id', (int) $tenantId);
            $results = $service->runAllAlerts((int) $tenantId);
            $this->displayResults((int) $tenantId, $results);

            return self::SUCCESS;
        }

        // Process all active tenants
        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id');

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $totalAlerts = 0;

        foreach ($tenants as $id) {
            app()->instance('current_tenant_id', $id);

            try {
                $results = $service->runAllAlerts($id);
                $total = array_sum($results);
                $totalAlerts += $total;

                if ($total > 0) {
                    $this->displayResults($id, $results);
                }
            } catch (\Throwable $e) {
                Log::error('ProcessPeripheralAlerts: Failed for tenant', [
                    'tenant_id' => $id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Tenant {$id}: Erro — {$e->getMessage()}");
            }
        }

        $this->info("Total: {$totalAlerts} alertas gerados para {$tenants->count()} tenants.");

        return self::SUCCESS;
    }

    private function displayResults(int $tenantId, array $results): void
    {
        $this->info("Tenant {$tenantId}:");
        $this->table(
            ['Tipo', 'Alertas'],
            collect($results)->map(fn ($count, $type) => [$type, $count])->values()->toArray()
        );
    }
}
