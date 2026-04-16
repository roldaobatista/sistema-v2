<?php

namespace App\Listeners;

use App\Events\ClockAdjustmentDecided;
use App\Models\Notification;
use App\Models\TimeClockAdjustment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyEmployeeOnAdjustmentDecision implements ShouldQueue
{
    public function handle(ClockAdjustmentDecided $event): void
    {
        $adjustment = $event->adjustment;
        $decision = $event->decision;

        app()->instance('current_tenant_id', $adjustment->tenant_id);

        $isApproved = $decision === 'approved';
        $title = $isApproved ? 'Ajuste de Ponto Aprovado' : 'Ajuste de Ponto Rejeitado';
        $message = $isApproved
            ? 'Seu ajuste de ponto foi aprovado.'
            : 'Seu ajuste de ponto foi rejeitado.'.($adjustment->rejection_reason ? " Motivo: {$adjustment->rejection_reason}" : '');

        try {
            Notification::notify(
                $adjustment->tenant_id,
                $adjustment->requested_by,
                'clock_adjustment_decided',
                $title,
                [
                    'message' => $message,
                    'icon' => $isApproved ? 'check-circle' : 'x-circle',
                    'color' => $isApproved ? 'success' : 'red',
                    'link' => '/rh/ajustes-ponto',
                    'notifiable_type' => TimeClockAdjustment::class,
                    'notifiable_id' => $adjustment->id,
                    'data' => [
                        'adjustment_id' => $adjustment->id,
                        'decision' => $decision,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("NotifyEmployeeOnAdjustmentDecision: falha para adjustment #{$adjustment->id}", ['error' => $e->getMessage()]);
        }
    }
}
