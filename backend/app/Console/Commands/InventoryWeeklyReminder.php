<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InventoryWeeklyReminder extends Command
{
    protected $signature = 'inventory:weekly-reminder';

    protected $description = 'Envia lembrete semanal para técnicos e motoristas realizarem inventário do estoque (PWA)';

    public function handle(): int
    {
        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->get();
        $count = 0;

        foreach ($tenants as $tenant) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $technicianWarehouses = Warehouse::where('tenant_id', $tenant->id)
                    ->where('type', Warehouse::TYPE_TECHNICIAN)
                    ->whereNotNull('user_id')
                    ->pluck('user_id')->unique()->filter();

                $vehicleWarehouses = Warehouse::where('tenant_id', $tenant->id)
                    ->where('type', Warehouse::TYPE_VEHICLE)
                    ->whereNotNull('vehicle_id')
                    ->with('vehicle:id,assigned_user_id')
                    ->get()
                    ->pluck('vehicle.assigned_user_id')
                    ->unique()
                    ->filter();

                $userIds = $technicianWarehouses->merge($vehicleWarehouses)->unique()->filter()->values();

                $activeUsers = User::whereIn('id', $userIds)
                    ->where('is_active', true)
                    ->pluck('id');

                foreach ($activeUsers as $userId) {
                    try {
                        Notification::notify(
                            $tenant->id,
                            $userId,
                            'inventory_weekly_reminder',
                            'Lembrete: realize a contagem do seu estoque esta semana',
                            [
                                'message' => 'Acesse o app e faça o inventário do seu estoque em Estoque > Meu inventário.',
                                'icon' => 'clipboard-check',
                                'link' => '/estoque/inventario-pwa',
                                'data' => ['reminder' => 'weekly'],
                            ]
                        );
                        $count++;
                    } catch (\Throwable $e) {
                        Log::warning("InventoryWeeklyReminder: notificação falhou user #{$userId}", ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("InventoryWeeklyReminder: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Lembretes de inventário semanal enviados: {$count}.");

        return self::SUCCESS;
    }
}
