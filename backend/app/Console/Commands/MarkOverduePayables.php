<?php

namespace App\Console\Commands;

use App\Models\AccountPayable;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentOverduePayable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkOverduePayables extends Command
{
    protected $signature = 'app:mark-overdue-payables';

    protected $description = 'Marca contas a pagar vencidas (pending + due_date < hoje) como overdue';

    public function handle(): int
    {
        $totalMarked = 0;

        Tenant::where('status', Tenant::STATUS_ACTIVE)->each(function (Tenant $tenant) use (&$totalMarked) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $overdue = AccountPayable::whereIn('status', [AccountPayable::STATUS_PENDING, AccountPayable::STATUS_PARTIAL])
                    ->where('due_date', '<', now()->startOfDay())
                    ->get();

                if ($overdue->isEmpty()) {
                    return;
                }

                AccountPayable::whereIn('id', $overdue->pluck('id'))
                    ->update(['status' => AccountPayable::STATUS_OVERDUE]);

                $managers = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenant->id))
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::ADMIN, Role::FINANCEIRO, Role::GERENTE]))
                    ->get();

                foreach ($overdue as $payable) {
                    foreach ($managers as $manager) {
                        try {
                            $manager->notify(new PaymentOverduePayable($payable));
                        } catch (\Throwable $e) {
                            Log::warning("MarkOverduePayables: notificação falhou para payable #{$payable->id}, user #{$manager->id}", ['error' => $e->getMessage()]);
                        }
                    }
                }

                $totalMarked += $overdue->count();
            } catch (\Throwable $e) {
                Log::error("MarkOverduePayables: falha no tenant #{$tenant->id}", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $this->error("Tenant #{$tenant->id}: {$e->getMessage()}");
            }
        });

        $this->info($totalMarked > 0
            ? "Marcadas {$totalMarked} conta(s) a pagar como vencida(s). Gestores notificados."
            : 'Nenhuma conta a pagar vencida encontrada.');

        return self::SUCCESS;
    }
}
