<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorVerificationCode extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $code
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código de Verificação 2FA')
            ->greeting('Olá!')
            ->line('Seu código de verificação para ativar a autenticação de dois fatores é:')
            ->line("**{$this->code}**")
            ->line('Este código expira em 10 minutos.')
            ->line('Se você não solicitou este código, ignore esta mensagem.');
    }
}
