<?php

namespace App\Listeners;

use App\Events\ClockEntryFlagged;
use App\Models\Notification;
use App\Models\TimeClockEntry;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyManagerOnClockFlag implements ShouldQueue
{
    public function handle(ClockEntryFlagged $event): void
    {
        $entry = $event->entry;
        $reason = $event->reason;

        app()->instance('current_tenant_id', $entry->tenant_id);

        $employee = User::find($entry->user_id);
        if (! $employee || ! $employee->manager_id) {
            return;
        }

        $reasonLabels = [
            'liveness_failed' => 'falha na verificação de vivacidade',
            'outside_geofence' => 'fora da área permitida (geofence)',
        ];

        $reasonLabel = $reasonLabels[$reason] ?? $reason;

        try {
            Notification::notify(
                $entry->tenant_id,
                $employee->manager_id,
                'clock_flag',
                'Ponto Marcado com Alerta',
                [
                    'message' => "{$employee->name} registrou ponto com alerta: {$reasonLabel}",
                    'icon' => 'alert-triangle',
                    'color' => 'amber',
                    'link' => '/rh/ponto',
                    'notifiable_type' => TimeClockEntry::class,
                    'notifiable_id' => $entry->id,
                    'data' => [
                        'entry_id' => $entry->id,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'reason' => $reason,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("NotifyManagerOnClockFlag: falha para entry #{$entry->id}", ['error' => $e->getMessage()]);
        }
    }
}
