<?php

namespace App\Notifications;

use App\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private EmployeeDocument $document,
        private int $daysUntilExpiry
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $categoryLabel = $this->document->getCategoryLabel();

        return (new MailMessage)
            ->subject("Documento {$categoryLabel} vencendo em {$this->daysUntilExpiry} dias")
            ->greeting("Olá {$notifiable->name},")
            ->line("O documento **{$categoryLabel}** está vencendo em **{$this->daysUntilExpiry} dias** (vencimento: {$this->document->expiry_date->format('d/m/Y')}).")
            ->line('Por favor, providencie a renovação o mais breve possível.')
            ->action('Ver Documentos', url('/rh/documentos'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_expiry',
            'document_id' => $this->document->id,
            'category' => $this->document->category,
            'expiry_date' => $this->document->expiry_date->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry,
            'message' => "Documento {$this->document->getCategoryLabel()} vence em {$this->daysUntilExpiry} dias.",
        ];
    }
}
