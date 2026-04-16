<?php

namespace App\Listeners;

use App\Events\ClockAdjustmentRequested;
use App\Models\Notification;
use App\Models\TimeClockAdjustment;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyManagerOnAdjustment implements ShouldQueue
{
    public function handle(ClockAdjustmentRequested $event): void
    {
        $adjustment = $event->adjustment;

        app()->instance('current_tenant_id', $adjustment->tenant_id);

        $requester = User::find($adjustment->requested_by);
        if (! $requester || ! $requester->manager_id) {
            return;
        }

        try {
            Notification::notify(
                $adjustment->tenant_id,
                $requester->manager_id,
                'clock_adjustment_requested',
                'Solicitação de Ajuste de Ponto',
                [
                    'message' => "{$requester->name} solicitou ajuste de ponto. Motivo: {$adjustment->reason}",
                    'icon' => 'edit',
                    'color' => 'amber',
                    'link' => '/rh/ajustes-ponto',
                    'notifiable_type' => TimeClockAdjustment::class,
                    'notifiable_id' => $adjustment->id,
                    'data' => [
                        'adjustment_id' => $adjustment->id,
                        'requester_id' => $requester->id,
                        'requester_name' => $requester->name,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("NotifyManagerOnAdjustment: falha para adjustment #{$adjustment->id}", ['error' => $e->getMessage()]);
        }
    }
}
