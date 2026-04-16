<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Search\WorkOrderRecurrenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessWorkOrderRecurrences extends Command
{
    protected $signature = 'app:process-work-order-recurrences';

    protected $description = 'Process all active work order recurrences and generate due OS';

    public function handle(WorkOrderRecurrenceService $service): int
    {
        $this->info('Iniciando processamento de recorrências de OS...');
        $totalGenerated = 0;

        Tenant::where('status', Tenant::STATUS_ACTIVE)
            ->each(function (Tenant $tenant) use ($service, &$totalGenerated) {
                try {
                    app()->instance('current_tenant_id', $tenant->id);
                    $count = $service->processAll();
                    $totalGenerated += $count;
                } catch (\Throwable $e) {
                    Log::error("ProcessWorkOrderRecurrences: falha no tenant #{$tenant->id}", [
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("Tenant #{$tenant->id}: {$e->getMessage()}");
                }
            });

        $this->info("Concluído. {$totalGenerated} Ordens de Serviço geradas.");

        return self::SUCCESS;
    }
}
