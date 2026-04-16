<?php

namespace App\Notifications;

use App\Models\TimeClockAdjustment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClockAdjustmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private TimeClockAdjustment $adjustment,
        private string $action // 'requested', 'approved', 'rejected'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            'requested' => 'Novo ajuste de ponto solicitado para aprovação.',
            'approved' => 'Seu ajuste de ponto foi aprovado.',
            'rejected' => 'Seu ajuste de ponto foi rejeitado.',
        ];

        return [
            'type' => 'clock_adjustment',
            'adjustment_id' => $this->adjustment->id,
            'action' => $this->action,
            'date' => $this->adjustment->clock_date ?? null,
            'message' => $messages[$this->action] ?? 'Atualização de ajuste de ponto.',
        ];
    }
}
