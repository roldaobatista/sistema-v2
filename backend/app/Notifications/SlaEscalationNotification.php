<?php

namespace App\Notifications;

use App\Models\SystemAlert;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaEscalationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public WorkOrder $workOrder,
        public array $escalation,
        public SystemAlert $alert,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $level = $this->escalation['level'] ?? 'warning';
        $percent = $this->escalation['percent_used'] ?? '?';
        $osNumber = $this->workOrder->business_number ?? $this->workOrder->id;

        return (new MailMessage)
            ->subject("SLA {$level}: OS #{$osNumber} em risco")
            ->greeting("Alerta de SLA — Nível: {$level}")
            ->line("A OS **#{$osNumber}** atingiu **{$percent}%** do tempo limite de SLA.")
            ->line('Cliente: '.($this->workOrder->customer->name ?? '—'))
            ->line('Status atual: '.($this->workOrder->status ?? '—'))
            ->action('Ver OS', url("/os/{$this->workOrder->id}"))
            ->line('Ação imediata é necessária para evitar breach de SLA.');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'sla_escalation',
            'level' => $this->escalation['level'],
            'work_order_id' => $this->workOrder->id,
            'message' => "SLA {$this->escalation['level']}: OS #{$this->workOrder->id} at {$this->escalation['percent_used']}%",
            'alert_id' => $this->alert->id,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
