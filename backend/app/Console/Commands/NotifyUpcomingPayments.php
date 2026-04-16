<?php

namespace App\Console\Commands;

use App\Models\AccountReceivable;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyUpcomingPayments extends Command
{
    protected $signature = 'notify:upcoming-payments {--days=3 : Days before due date}';

    protected $description = 'Send notifications for payments due soon';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $targetDate = now()->addDays($days)->toDateString();

        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->get();
        $totalNotified = 0;

        foreach ($tenants as $tenant) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $upcomingAR = AccountReceivable::where('tenant_id', $tenant->id)
                    ->where('status', AccountReceivable::STATUS_PENDING)
                    ->whereDate('due_date', $targetDate)
                    ->get();

                if ($upcomingAR->isEmpty()) {
                    continue;
                }

                $admins = User::where('tenant_id', $tenant->id)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN, Role::FINANCEIRO, Role::GERENTE]))
                    ->get();

                foreach ($upcomingAR as $ar) {
                    try {
                        foreach ($admins as $admin) {
                            try {
                                Notification::notify(
                                    $tenant->id,
                                    $admin->id,
                                    'payment_upcoming',
                                    'Pagamento Próximo do Vencimento',
                                    [
                                        'message' => "Conta a receber \"{$ar->description}\" vence em {$days} dias (R$ ".number_format((float) $ar->amount, 2, ',', '.').').',
                                        'icon' => 'clock',
                                        'color' => 'warning',
                                        'data' => ['account_receivable_id' => $ar->id, 'due_date' => $ar->due_date->format('d/m/Y')],
                                    ]
                                );
                            } catch (\Throwable $e) {
                                Log::warning("NotifyUpcomingPayments: notificação falhou ar #{$ar->id}, user #{$admin->id}", ['error' => $e->getMessage()]);
                            }
                        }
                        $totalNotified++;
                    } catch (\Throwable $e) {
                        Log::warning("NotifyUpcomingPayments: falha ao processar ar #{$ar->id}", ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("NotifyUpcomingPayments: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Notificações enviadas para {$totalNotified} contas a vencer.");

        return self::SUCCESS;
    }
}
