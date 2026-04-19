<?php

namespace App\Listeners;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemType;
use App\Events\CalibrationExpiring;
use App\Models\AgendaItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class CreateAgendaItemOnCalibration implements ShouldQueue
{
    public function handle(CalibrationExpiring $event): void
    {
        $calibration = $event->calibration;
        $equipment = $calibration->equipment;

        if (! $equipment) {
            Log::info('CreateAgendaItemOnCalibration: equipamento não encontrado', ['calibration_id' => $calibration->id]);

            return;
        }

        app()->instance('current_tenant_id', $calibration->tenant_id);

        try {
            $responsavel = $equipment->responsible_user_id;

            AgendaItem::criarDeOrigem(
                model: $equipment,
                tipo: AgendaItemType::CALIBRACAO,
                title: "Calibração vencendo — {$equipment->code} ({$equipment->brand} {$equipment->model})",
                responsavelId: $responsavel,
                extras: [
                    'priority' => AgendaItemPriority::ALTA,
                    'due_at' => $equipment->next_calibration_at,
                    'short_description' => "Cliente: {$equipment->customer?->name}",
                    'context' => [
                        'equipamento_id' => $equipment->id,
                        'codigo' => $equipment->code,
                        'cliente' => $equipment->customer?->name,
                        'proxima_calibracao' => $equipment->next_calibration_at?->toDateString(),
                        'link' => "/equipamentos/{$equipment->id}",
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('CreateAgendaItemOnCalibration failed', ['calibration_id' => $calibration->id, 'error' => $e->getMessage()]);
        }
    }
}
