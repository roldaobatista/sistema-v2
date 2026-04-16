<?php

namespace App\Listeners;

use App\Events\ClockEntryRegistered;
use App\Models\Notification;
use App\Models\TimeClockEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendClockComprovante implements ShouldQueue
{
    public function handle(ClockEntryRegistered $event): void
    {
        $entry = $event->entry;
        $type = $event->type;

        app()->instance('current_tenant_id', $entry->tenant_id);

        $typeLabels = [
            'clock_in' => 'Entrada',
            'clock_out' => 'Saída',
            'break_start' => 'Início Intervalo',
            'break_end' => 'Fim Intervalo',
        ];

        $label = $typeLabels[$type] ?? $type;
        $time = $type === 'clock_out' ? $entry->clock_out : $entry->clock_in;

        try {
            Notification::notify(
                $entry->tenant_id,
                $entry->user_id,
                'clock_comprovante',
                "Comprovante de Ponto: {$label}",
                [
                    'message' => "Registro de {$label} em ".($time ? $time->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s')),
                    'icon' => 'clock',
                    'color' => 'brand',
                    'link' => '/rh/ponto',
                    'notifiable_type' => TimeClockEntry::class,
                    'notifiable_id' => $entry->id,
                    'data' => [
                        'entry_id' => $entry->id,
                        'type' => $type,
                        'time' => $time?->toISOString(),
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("SendClockComprovante: falha para entry #{$entry->id}", ['error' => $e->getMessage()]);
        }
    }
}
