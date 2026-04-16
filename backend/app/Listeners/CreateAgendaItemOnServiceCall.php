<?php

namespace App\Listeners;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Enums\ServiceCallStatus;
use App\Events\ServiceCallCreated;
use App\Events\ServiceCallStatusChanged;
use App\Models\AgendaItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class CreateAgendaItemOnServiceCall implements ShouldQueue
{
    public function handleCreated(ServiceCallCreated $event): void
    {
        $sc = $event->serviceCall;
        app()->instance('current_tenant_id', $sc->tenant_id);

        try {
            $responsavel = $sc->technician_id ?? $sc->created_by;

            AgendaItem::criarDeOrigem(
                model: $sc,
                tipo: AgendaItemType::CHAMADO,
                titulo: "Chamado #{$sc->call_number} — {$sc->customer?->name}",
                responsavelId: $responsavel,
                extras: [
                    'prioridade' => match ($sc->priority ?? 'normal') {
                        'urgent' => AgendaItemPriority::URGENTE,
                        'high' => AgendaItemPriority::ALTA,
                        default => AgendaItemPriority::MEDIA,
                    },
                    'due_at' => $sc->scheduled_date,
                    'descricao_curta' => $sc->observations,
                    'contexto' => [
                        'chamado_id' => $sc->id,
                        'cliente' => $sc->customer?->name,
                        'link' => "/chamados/{$sc->id}",
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('CreateAgendaItemOnServiceCall: created failed', ['sc_id' => $sc->id, 'error' => $e->getMessage()]);
        }
    }

    public function handleStatusChanged(ServiceCallStatusChanged $event): void
    {
        $sc = $event->serviceCall;
        app()->instance('current_tenant_id', $sc->tenant_id);

        try {
            $finalStatuses = [ServiceCallStatus::CONVERTED_TO_OS->value, ServiceCallStatus::CANCELLED->value];

            $toStatusValue = $event->toStatus instanceof ServiceCallStatus
                ? $event->toStatus->value
                : (string) $event->toStatus;

            if (in_array($toStatusValue, $finalStatuses, true)) {
                AgendaItem::syncFromSource($sc, [
                    'status' => $toStatusValue === ServiceCallStatus::CANCELLED->value
                        ? AgendaItemStatus::CANCELADO
                        : AgendaItemStatus::CONCLUIDO,
                    'closed_at' => now(),
                    'closed_by' => $event->user?->id,
                ]);
            } else {
                AgendaItem::syncFromSource($sc, [
                    'status' => AgendaItemStatus::EM_ANDAMENTO,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('CreateAgendaItemOnServiceCall: statusChanged failed', ['sc_id' => $sc->id, 'error' => $e->getMessage()]);
        }
    }

    public function subscribe($events): array
    {
        return [
            ServiceCallCreated::class => 'handleCreated',
            ServiceCallStatusChanged::class => 'handleStatusChanged',
        ];
    }
}
