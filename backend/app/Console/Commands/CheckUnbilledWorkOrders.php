<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckUnbilledWorkOrders extends Command
{
    protected $signature = 'work-orders:check-unbilled {--days=3 : Days after completion without billing to alert}';

    protected $description = 'Alert about completed work orders without billing (CRITICAL business alert)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->subDays($days);

        $activeTenantIds = Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id');

        // LEI 4 JUSTIFICATIVA: comando agendado cross-tenant (sem user autenticado); itera sobre tenants ativos via whereIn(tenant_id). Soft-delete removido para detectar OS concluídas mesmo após deleção lógica recente.
        $workOrders = WorkOrder::withoutGlobalScopes()
            ->whereIn('tenant_id', $activeTenantIds)
            ->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED])
            ->where(function ($q) use ($threshold) {
                $q->where(function ($q2) use ($threshold) {
                    $q2->where('status', WorkOrder::STATUS_COMPLETED)
                        ->where('completed_at', '<=', $threshold);
                })->orWhere(function ($q2) use ($threshold) {
                    $q2->where('status', WorkOrder::STATUS_DELIVERED)
                        ->where('delivered_at', '<=', $threshold);
                });
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('invoices')
                    ->whereColumn('invoices.work_order_id', 'work_orders.id');
            })
            ->with(['customer:id,name', 'assignee:id,name'])
            ->get();

        $count = 0;
        $adminsByTenant = [];
        foreach ($workOrders as $wo) {
            try {
                $refDate = $wo->status === WorkOrder::STATUS_DELIVERED ? $wo->delivered_at : $wo->completed_at;
                $daysSinceCompletion = $refDate ? (int) $refDate->diffInDays(now()) : 0;
                app()->instance('current_tenant_id', $wo->tenant_id);

                if (! isset($adminsByTenant[$wo->tenant_id])) {
                    // LEI 4 JUSTIFICATIVA: command precisa buscar admins de qualquer tenant para notificá-los; tenant_id é filtrado explicitamente via where abaixo.
                    $adminsByTenant[$wo->tenant_id] = User::withoutGlobalScopes()
                        ->where('tenant_id', $wo->tenant_id)
                        ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN, Role::FINANCEIRO]))
                        ->get();
                }

                foreach ($adminsByTenant[$wo->tenant_id] as $admin) {
                    try {
                        // LEI 4 JUSTIFICATIVA: verificação de duplicidade de notificação em comando scheduled; user_id filtra implicitamente por tenant (admin pertence ao tenant iterado no loop externo).
                        $existing = Notification::withoutGlobalScopes()
                            ->where('user_id', $admin->id)
                            ->where('notifiable_type', WorkOrder::class)
                            ->where('notifiable_id', $wo->id)
                            ->where('type', 'unbilled_work_order')
                            ->where('created_at', '>=', now()->subDays(1))
                            ->exists();

                        if ($existing) {
                            continue;
                        }

                        $woNumber = $wo->os_number ?? $wo->number;
                        Notification::notify(
                            $wo->tenant_id,
                            $admin->id,
                            'unbilled_work_order',
                            '🔴 OS concluída sem faturamento',
                            [
                                'message' => "OS {$woNumber} (cliente: {$wo->customer?->name}) ".($wo->status === WorkOrder::STATUS_DELIVERED ? 'entregue' : 'concluída')." há {$daysSinceCompletion} dia(s) sem faturamento",
                                'notifiable_type' => WorkOrder::class,
                                'notifiable_id' => $wo->id,
                            ]
                        );
                        $count++;
                    } catch (\Throwable $e) {
                        Log::warning("CheckUnbilledWorkOrders: notificação falhou para WO #{$wo->id}, user #{$admin->id}", ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("CheckUnbilledWorkOrders: falha ao processar WO #{$wo->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Sent {$count} unbilled work order alert(s) for {$workOrders->count()} WO(s).");

        return self::SUCCESS;
    }
}
