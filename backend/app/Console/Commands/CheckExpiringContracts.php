<?php

namespace App\Console\Commands;

use App\Events\ContractRenewing;
use App\Models\Notification;
use App\Models\RecurringContract;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiringContracts extends Command
{
    protected $signature = 'contracts:check-expiring {--days=7 : Days before expiry to alert}';

    protected $description = 'Alert about recurring contracts expiring within N days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->addDays($days);

        $activeTenantIds = Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id');

        $contracts = RecurringContract::withoutGlobalScopes()
            ->whereIn('tenant_id', $activeTenantIds)
            ->where('is_active', true)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), $threshold])
            ->with(['customer:id,name'])
            ->get();

        $count = 0;
        $adminsByTenant = [];
        foreach ($contracts as $contract) {
            try {
                $daysLeft = (int) now()->diffInDays($contract->end_date);
                app()->instance('current_tenant_id', $contract->tenant_id);

                ContractRenewing::dispatch($contract, $daysLeft);

                if (! isset($adminsByTenant[$contract->tenant_id])) {
                    $adminsByTenant[$contract->tenant_id] = User::withoutGlobalScopes()
                        ->where('tenant_id', $contract->tenant_id)
                        ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN, Role::FINANCEIRO]))
                        ->get();
                }

                foreach ($adminsByTenant[$contract->tenant_id] as $admin) {
                    $existing = Notification::withoutGlobalScopes()
                        ->where('tenant_id', $contract->tenant_id)
                        ->where('user_id', $admin->id)
                        ->where('notifiable_type', RecurringContract::class)
                        ->where('notifiable_id', $contract->id)
                        ->where('type', 'contract_expiring')
                        ->where('created_at', '>=', now()->subDays(3))
                        ->exists();

                    if ($existing) {
                        continue;
                    }

                    try {
                        Notification::notify(
                            $contract->tenant_id,
                            $admin->id,
                            'contract_expiring',
                            'Contrato vencendo',
                            [
                                'message' => "Contrato #{$contract->id} do cliente {$contract->customer?->name} vence em {$daysLeft} dia(s) ({$contract->end_date->format('d/m/Y')})",
                                'notifiable_type' => RecurringContract::class,
                                'notifiable_id' => $contract->id,
                            ]
                        );
                        $count++;
                    } catch (\Throwable $e) {
                        Log::warning("CheckExpiringContracts: notificação falhou contract #{$contract->id}, user #{$admin->id}", ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("CheckExpiringContracts: falha ao processar contract #{$contract->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Sent {$count} expiring contract alert(s) for {$contracts->count()} contract(s).");

        return self::SUCCESS;
    }
}
