<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        private LeaveRequest $leave,
        private string $action // 'approved', 'rejected'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->action === 'approved' ? 'aprovada' : 'rejeitada';
        $typeLabel = $this->leave->getTypeLabel();

        return (new MailMessage)
            ->subject("Solicitação de {$typeLabel} {$status}")
            ->greeting("Olá {$notifiable->name},")
            ->line("Sua solicitação de **{$typeLabel}** de {$this->leave->start_date->format('d/m/Y')} a {$this->leave->end_date->format('d/m/Y')} foi **{$status}**.")
            ->action('Ver Solicitações', url('/rh/licencas'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_approval',
            'leave_id' => $this->leave->id,
            'leave_type' => $this->leave->type,
            'action' => $this->action,
            'start_date' => $this->leave->start_date->toDateString(),
            'end_date' => $this->leave->end_date->toDateString(),
            'message' => "Solicitação de {$this->leave->getTypeLabel()} ".($this->action === 'approved' ? 'aprovada' : 'rejeitada').'.',
        ];
    }
}
