<?php

namespace App\Console\Commands;

use App\Models\AccountReceivable;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkOverdueReceivables extends Command
{
    protected $signature = 'app:mark-overdue-receivables';

    protected $description = 'Marca títulos a receber vencidos (pending + due_date < hoje) como overdue';

    public function handle(): int
    {
        $totalMarked = 0;

        Tenant::where('status', Tenant::STATUS_ACTIVE)->each(function (Tenant $tenant) use (&$totalMarked) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $overdue = AccountReceivable::whereIn('status', [AccountReceivable::STATUS_PENDING, AccountReceivable::STATUS_PARTIAL])
                    ->where('due_date', '<', now()->startOfDay())
                    ->with('customer:id,name')
                    ->get();

                if ($overdue->isEmpty()) {
                    return;
                }

                AccountReceivable::whereIn('id', $overdue->pluck('id'))
                    ->update(['status' => AccountReceivable::STATUS_OVERDUE]);

                $managers = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenant->id))
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::ADMIN, Role::FINANCEIRO, Role::GERENTE]))
                    ->get();

                foreach ($overdue as $receivable) {
                    foreach ($managers as $manager) {
                        try {
                            $manager->notify(new PaymentOverdue($receivable));
                        } catch (\Throwable $e) {
                            Log::warning("MarkOverdueReceivables: notificação falhou para receivable #{$receivable->id}, user #{$manager->id}", ['error' => $e->getMessage()]);
                        }
                    }
                }

                $totalMarked += $overdue->count();
            } catch (\Throwable $e) {
                Log::error("MarkOverdueReceivables: falha no tenant #{$tenant->id}", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $this->error("Tenant #{$tenant->id}: {$e->getMessage()}");
            }
        });

        $this->info($totalMarked > 0
            ? "Marcados {$totalMarked} título(s) como vencido(s). Gestores notificados."
            : 'Nenhum título vencido encontrado.');

        return self::SUCCESS;
    }
}
