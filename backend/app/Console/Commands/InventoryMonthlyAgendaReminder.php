<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InventoryMonthlyAgendaReminder extends Command
{
    protected $signature = 'inventory:monthly-central-reminder';

    protected $description = 'Envia lembrete mensal para estoquista realizar inventário do estoque central';

    public function handle(): int
    {
        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->get();
        $count = 0;

        foreach ($tenants as $tenant) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $centralExists = Warehouse::where('tenant_id', $tenant->id)
                    ->where('type', Warehouse::TYPE_FIXED)
                    ->whereNull('user_id')
                    ->whereNull('vehicle_id')
                    ->exists();

                if (! $centralExists) {
                    continue;
                }

                $estoquistas = User::where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->role('estoquista')
                    ->pluck('id');

                foreach ($estoquistas as $userId) {
                    try {
                        Notification::notify(
                            $tenant->id,
                            $userId,
                            'inventory_monthly_central_reminder',
                            'Lembrete: inventário mensal do estoque central',
                            [
                                'message' => 'Realize a contagem do estoque central este mês em Estoque > Inventários.',
                                'icon' => 'clipboard-check',
                                'link' => '/estoque/inventarios',
                                'data' => ['reminder' => 'monthly_central'],
                            ]
                        );
                        $count++;
                    } catch (\Throwable $e) {
                        Log::warning("InventoryMonthlyAgendaReminder: notificação falhou user #{$userId}", ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("InventoryMonthlyAgendaReminder: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Lembretes de inventário mensal (central) enviados: {$count}.");

        return self::SUCCESS;
    }
}
