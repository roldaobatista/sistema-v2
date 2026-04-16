<?php

namespace App\Listeners;

use App\Events\LeaveRequested;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyManagerOnLeave implements ShouldQueue
{
    public function handle(LeaveRequested $event): void
    {
        $leave = $event->leave;

        app()->instance('current_tenant_id', $leave->tenant_id);

        $employee = User::find($leave->user_id);
        if (! $employee || ! $employee->manager_id) {
            return;
        }

        $period = $leave->start_date->format('d/m/Y').' a '.$leave->end_date->format('d/m/Y');

        try {
            Notification::notify(
                $leave->tenant_id,
                $employee->manager_id,
                'leave_requested',
                'Solicitação de Afastamento',
                [
                    'message' => "{$employee->name} solicitou {$leave->getTypeLabel()} de {$period} ({$leave->days_count} dias)",
                    'icon' => 'calendar-off',
                    'color' => 'amber',
                    'link' => '/rh/afastamentos',
                    'notifiable_type' => LeaveRequest::class,
                    'notifiable_id' => $leave->id,
                    'data' => [
                        'leave_id' => $leave->id,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'type' => $leave->type,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("NotifyManagerOnLeave: falha para leave #{$leave->id}", ['error' => $e->getMessage()]);
        }
    }
}
