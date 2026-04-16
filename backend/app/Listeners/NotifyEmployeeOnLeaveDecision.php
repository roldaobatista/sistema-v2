<?php

namespace App\Listeners;

use App\Events\LeaveDecided;
use App\Models\LeaveRequest;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyEmployeeOnLeaveDecision implements ShouldQueue
{
    public function handle(LeaveDecided $event): void
    {
        $leave = $event->leave;
        $decision = $event->decision;

        app()->instance('current_tenant_id', $leave->tenant_id);

        $isApproved = $decision === 'approved';
        $title = $isApproved ? 'Afastamento Aprovado' : 'Afastamento Rejeitado';
        $message = $isApproved
            ? "Seu afastamento ({$leave->getTypeLabel()}) foi aprovado."
            : "Seu afastamento ({$leave->getTypeLabel()}) foi rejeitado.".($leave->rejection_reason ? " Motivo: {$leave->rejection_reason}" : '');

        try {
            Notification::notify(
                $leave->tenant_id,
                $leave->user_id,
                'leave_decided',
                $title,
                [
                    'message' => $message,
                    'icon' => $isApproved ? 'check-circle' : 'x-circle',
                    'color' => $isApproved ? 'success' : 'red',
                    'link' => '/rh/afastamentos',
                    'notifiable_type' => LeaveRequest::class,
                    'notifiable_id' => $leave->id,
                    'data' => [
                        'leave_id' => $leave->id,
                        'decision' => $decision,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("NotifyEmployeeOnLeaveDecision: falha para leave #{$leave->id}", ['error' => $e->getMessage()]);
        }
    }
}
