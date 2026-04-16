<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\RecurringContract;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRecurringWorkOrders extends Command
{
    protected $signature = 'app:generate-recurring-work-orders';

    protected $description = 'Gera OS automáticas para contratos recorrentes com next_run_date <= hoje';

    public function handle(): int
    {
        $totalGenerated = 0;

        Tenant::where('status', Tenant::STATUS_ACTIVE)->each(function (Tenant $tenant) use (&$totalGenerated) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $contracts = RecurringContract::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->where('next_run_date', '<=', now()->toDateString())
                    ->with('items')
                    ->get();

                if ($contracts->isEmpty()) {
                    return;
                }

                foreach ($contracts as $contract) {
                    try {
                        $wo = $contract->generateWorkOrder();
                        $this->line("Contrato #{$contract->id} \"{$contract->name}\" -> OS #{$wo->business_number}");
                        $totalGenerated++;

                        // Notificar técnico e criador
                        $notifyIds = array_filter(array_unique([
                            $wo->assigned_to,
                            $contract->created_by,
                        ]));
                        foreach ($notifyIds as $uid) {
                            try {
                                Notification::notify(
                                    $tenant->id,
                                    $uid,
                                    'recurring_os_generated',
                                    "Nova OS {$wo->business_number} gerada automaticamente",
                                    [
                                        'message' => "Contrato recorrente: {$contract->name}",
                                        'work_order_id' => $wo->id,
                                        'link' => "/os/{$wo->id}",
                                    ]
                                );
                            } catch (\Throwable $e) {
                                Log::warning("GenerateRecurringWorkOrders: notificação falhou para contract #{$contract->id}, user #{$uid}", ['error' => $e->getMessage()]);
                            }
                        }
                    } catch (\Throwable $e) {
                        $this->error("Contrato #{$contract->id}: {$e->getMessage()}");
                        Log::error("GenerateRecurringWorkOrders: falha ao gerar OS para contrato #{$contract->id}", ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("GenerateRecurringWorkOrders: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
                $this->error("Tenant #{$tenant->id}: {$e->getMessage()}");
            }
        });

        $this->info($totalGenerated > 0
            ? "Total: {$totalGenerated} OS geradas."
            : 'Nenhum contrato recorrente pendente.');

        return self::SUCCESS;
    }
}
