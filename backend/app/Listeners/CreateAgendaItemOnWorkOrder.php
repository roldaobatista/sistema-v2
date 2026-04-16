<?php

namespace App\Listeners;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Events\WorkOrderCompleted;
use App\Events\WorkOrderStarted;
use App\Models\AgendaItem;
use App\Models\WorkOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class CreateAgendaItemOnWorkOrder implements ShouldQueue
{
    public function handleWorkOrderStarted(WorkOrderStarted $event): void
    {
        $wo = $event->workOrder;
        app()->instance('current_tenant_id', $wo->tenant_id);

        try {
            $responsavel = $wo->assigned_to ?? $wo->created_by;

            AgendaItem::criarDeOrigem(
                model: $wo,
                tipo: AgendaItemType::OS,
                titulo: "OS #{$wo->business_number} — {$wo->customer?->name}",
                responsavelId: $responsavel,
                extras: [
                    'prioridade' => $wo->priority === WorkOrder::PRIORITY_URGENT
                        ? AgendaItemPriority::URGENTE
                        : AgendaItemPriority::MEDIA,
                    'due_at' => $wo->received_at,
                    'contexto' => [
                        'numero' => $wo->business_number,
                        'cliente' => $wo->customer?->name,
                        'link' => "/os/{$wo->id}",
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('CreateAgendaItemOnWorkOrder: started failed', ['wo_id' => $wo->id, 'error' => $e->getMessage()]);
        }
    }

    public function handleWorkOrderCompleted(WorkOrderCompleted $event): void
    {
        $wo = $event->workOrder;
        app()->instance('current_tenant_id', $wo->tenant_id);

        try {
            AgendaItem::syncFromSource($wo, [
                'status' => AgendaItemStatus::CONCLUIDO,
                'closed_at' => now(),
                'closed_by' => $event->user?->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('CreateAgendaItemOnWorkOrder: completed failed', ['wo_id' => $wo->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Registra quais eventos este listener escuta.
     */
    public function subscribe($events): array
    {
        return [
            WorkOrderStarted::class => 'handleWorkOrderStarted',
            WorkOrderCompleted::class => 'handleWorkOrderCompleted',
        ];
    }
}
