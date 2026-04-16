<?php

namespace App\Notifications;

use App\Models\VacationBalance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VacationExpirationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private VacationBalance $balance,
        private int $daysUntilDeadline
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Férias vencendo em {$this->daysUntilDeadline} dias — Ação necessária")
            ->greeting("Olá {$notifiable->name},")
            ->line("Suas férias referentes ao período aquisitivo {$this->balance->acquisition_start->format('d/m/Y')} - {$this->balance->acquisition_end->format('d/m/Y')} vencem em **{$this->daysUntilDeadline} dias**.")
            ->line("Você possui **{$this->balance->remaining_days} dias** restantes.")
            ->line('**Atenção:** Férias vencidas podem gerar pagamento em dobro (CLT art. 137).')
            ->action('Ver Férias', url('/rh/ferias'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'vacation_expiration',
            'vacation_balance_id' => $this->balance->id,
            'remaining_days' => $this->balance->remaining_days,
            'deadline' => $this->balance->deadline->toDateString(),
            'days_until_deadline' => $this->daysUntilDeadline,
            'message' => "Férias vencem em {$this->daysUntilDeadline} dias ({$this->balance->remaining_days} dias restantes).",
        ];
    }
}
