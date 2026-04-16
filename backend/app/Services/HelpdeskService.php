<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HelpdeskService
{
    /**
     * Altera o status de um ticket do Helpdesk processando regras de SLA e ciclo de vida.
     */
    public function changeTicketStatus(int $ticketId, string $newStatus, int $userId): void
    {
        DB::transaction(function () use ($ticketId, $newStatus) {
            $ticket = DB::table('portal_tickets')->where('id', $ticketId)->lockForUpdate()->first();

            if (! $ticket || $ticket->status === $newStatus) {
                return;
            }

            $updateData = [
                'status' => $newStatus,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ];

            // 1. Pausa do SLA
            if ($newStatus === 'waiting_customer') {
                $updateData['paused_at'] = now()->format('Y-m-d H:i:s');
            }

            // 2. Retomada do SLA
            if ($ticket->status === 'waiting_customer' && $newStatus !== 'waiting_customer') {
                if ($ticket->paused_at && $ticket->sla_due_at) {
                    $paused = Carbon::parse($ticket->paused_at);
                    $due = Carbon::parse($ticket->sla_due_at);

                    $diff = $paused->diffInMinutes(now());

                    $updateData['sla_due_at'] = $due->addMinutes($diff)->format('Y-m-d H:i:s');
                }
                $updateData['paused_at'] = null;
            }

            // 3. Resolução & Fechamento
            $resolvedStatuses = ['resolved', 'closed'];
            if (in_array($newStatus, $resolvedStatuses, true)) {
                if (! $ticket->resolved_at) {
                    $updateData['resolved_at'] = now()->format('Y-m-d H:i:s');
                }
            } else {
                // Se reaberto ou modificado a partir de resolvido, perde a data de resolução
                $updateData['resolved_at'] = null;
            }

            DB::table('portal_tickets')->where('id', $ticketId)->update($updateData);
        });
    }
}
