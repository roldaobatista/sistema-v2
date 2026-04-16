<?php

namespace App\Notifications;

use App\Models\Notification;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Str;

class WorkOrderStatusChanged extends BaseNotification
{
    use Queueable;

    private const STATUS_LABELS = [
        'open' => 'Aberta',
        'in_progress' => 'Em Andamento',
        'in_displacement' => 'Em Deslocamento',
        'at_client' => 'No Cliente',
        'in_service' => 'Em Atendimento',
        'awaiting_return' => 'Aguardando Retorno',
        'in_return' => 'Em Retorno',
        'waiting_parts' => 'Aguardando Peças',
        'waiting_approval' => 'Aguardando Aprovação',
        'completed' => 'Concluída',
        'delivered' => 'Entregue',
        'invoiced' => 'Faturada',
        'cancelled' => 'Cancelada',
    ];

    public function __construct(
        public WorkOrder $workOrder,
        public string $oldStatus,
        public string $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $newLabel = self::STATUS_LABELS[$this->newStatus] ?? $this->newStatus;
        $wo = $this->workOrder;

        return (new MailMessage)
            ->subject("OS #{$wo->business_number} — Status: {$newLabel}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("A OS **#{$wo->business_number}** teve o status alterado para **{$newLabel}**.")
            ->line("**Cliente:** {$wo->customer?->name}")
            ->line('**Descrição:** '.Str::limit($wo->description, 100))
            ->action('Ver OS', config('app.frontend_url')."/os/{$wo->id}")
            ->line('Você recebeu este e-mail por ser responsável ou criador desta OS.');
    }

    /**
     * Persist notification using KALIBRIUM's custom Notification model
     * instead of Laravel's built-in database channel.
     */
    public function persistToDatabase(int $tenantId, int $userId): Notification
    {
        $newLabel = self::STATUS_LABELS[$this->newStatus] ?? $this->newStatus;
        $wo = $this->workOrder;

        return Notification::notify(
            $tenantId,
            $userId,
            'work_order_status_changed',
            "OS #{$wo->business_number} — {$newLabel}",
            [
                'icon' => 'clipboard-check',
                'color' => match ($this->newStatus) {
                    'completed' => 'green',
                    'cancelled' => 'red',
                    'delivered' => 'blue',
                    'invoiced' => 'emerald',
                    default => 'brand',
                },
                'link' => "/os/{$wo->id}",
                'notifiable_type' => WorkOrder::class,
                'notifiable_id' => $wo->id,
                'message' => 'Status alterado de '.(self::STATUS_LABELS[$this->oldStatus] ?? $this->oldStatus)." para {$newLabel}",
                'data' => [
                    'work_order_id' => $wo->id,
                    'number' => $wo->business_number,
                    'old_status' => $this->oldStatus,
                    'new_status' => $this->newStatus,
                ],
            ]
        );
    }
}
