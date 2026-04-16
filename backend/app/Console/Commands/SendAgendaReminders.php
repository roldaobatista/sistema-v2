<?php

namespace App\Console\Commands;

use App\Enums\AgendaItemStatus;
use App\Models\AgendaItem;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAgendaReminders extends Command
{
    protected $signature = 'central:send-reminders';

    protected $description = 'Envia notificações para itens da Agenda cujo remind_at passou';

    public function handle(): int
    {
        $sent = 0;

        // Scheduler não tem contexto de tenant → BelongsToTenant scope bloquearia a query.
        // Iterar por tenant e setar contexto antes de consultar.
        Tenant::where('status', Tenant::STATUS_ACTIVE)
            ->pluck('id')
            ->each(function (int $tenantId) use (&$sent) {
                try {
                    app()->instance('current_tenant_id', $tenantId);

                    $items = AgendaItem::query()
                        ->with(['responsavel:id,name'])
                        ->whereNotNull('remind_at')
                        ->whereNull('remind_notified_at')
                        ->where('remind_at', '<=', now())
                        ->whereNotIn('status', [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO])
                        ->get();

                    foreach ($items as $item) {
                        try {
                            if (! $item->responsavel_user_id) {
                                continue;
                            }
                            $item->gerarNotificacao(
                                'central_reminder',
                                'Lembrete: '.$item->titulo,
                                $item->descricao_curta ?: 'Horário do lembrete chegou.',
                                ['remind_at' => $item->remind_at?->toIso8601String()],
                                ['icon' => 'clock', 'color' => 'amber']
                            );
                            $item->update(['remind_notified_at' => now()]);
                            $sent++;
                        } catch (\Throwable $e) {
                            Log::warning("SendAgendaReminders: falha ao processar item #{$item->id}", ['error' => $e->getMessage()]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("SendAgendaReminders: falha no tenant #{$tenantId}", ['error' => $e->getMessage()]);
                }
            });

        if ($sent > 0) {
            $this->info("Enviadas {$sent} notificações de lembrete.");
        }

        return Command::SUCCESS;
    }
}
