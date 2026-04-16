<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EspelhoAvailableNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $referenceMonth,
        public int $confirmationId
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'espelho_available',
            'title' => 'Espelho de Ponto Disponível',
            'message' => "O espelho de ponto referente a {$this->referenceMonth} está disponível para conferência.",
            'confirmation_id' => $this->confirmationId,
            'reference_month' => $this->referenceMonth,
        ];
    }
}
