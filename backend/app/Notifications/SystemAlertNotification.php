<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $title,
        private string $body,
        private string $severity = 'error'
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Kalibrium] '.$this->severity.': '.$this->title)
            ->line($this->body)
            ->line('Servidor: '.config('app.url'))
            ->line('Horário: '.now()->format('d/m/Y H:i:s'));
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'severity' => $this->severity,
        ];
    }
}
