<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateCalibrationAlerts extends Command
{
    protected $signature = 'calibration:alerts {--days=30 : Dias para frente}';

    protected $description = 'Gera notificações para calibrações vencendo ou vencidas';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $totalCreated = 0;
        $totalEquipments = 0;

        Tenant::where('status', Tenant::STATUS_ACTIVE)->each(function (Tenant $tenant) use ($days, &$totalCreated, &$totalEquipments) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $equipments = Equipment::with('customer:id,name')
                    ->calibrationDue($days)
                    ->active()
                    ->get();

                if ($equipments->isEmpty()) {
                    return;
                }

                $totalEquipments += $equipments->count();

                // Query admins once per tenant instead of per equipment
                $users = User::where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN, Role::GERENTE]))
                    ->get();

                foreach ($equipments as $eq) {
                    try {
                        $daysRemaining = (int) now()->diffInDays($eq->next_calibration_at, false);

                        // Evitar duplicatas: não criar se já existe notificação do mesmo tipo nos últimos 7 dias
                        $exists = Notification::where('notifiable_type', Equipment::class)
                            ->where('notifiable_id', $eq->id)
                            ->where('type', $daysRemaining < 0 ? 'calibration_overdue' : 'calibration_due')
                            ->where('created_at', '>=', now()->subDays(7))
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        foreach ($users as $user) {
                            try {
                                Notification::calibrationDue($eq, $user->id, $daysRemaining);
                                $totalCreated++;
                            } catch (\Throwable $e) {
                                Log::warning("GenerateCalibrationAlerts: notificação falhou eq #{$eq->id}, user #{$user->id}", ['error' => $e->getMessage()]);
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning("GenerateCalibrationAlerts: falha ao processar eq #{$eq->id}", ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("GenerateCalibrationAlerts: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
            }
        });

        $this->info("{$totalCreated} notificações criadas para {$totalEquipments} equipamentos.");

        return Command::SUCCESS;
    }
}
