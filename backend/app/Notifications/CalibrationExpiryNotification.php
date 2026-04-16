<?php

namespace App\Notifications;

use App\Channels\CustomDatabaseChannel;
use App\Models\Equipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalibrationExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Equipment $equipment,
        private int $daysUntilExpiry,
        private ?int $tenantId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [CustomDatabaseChannel::class, 'mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $equipName = $this->equipment->name ?? $this->equipment->serial_number ?? "#{$this->equipment->id}";
        $customer = $this->equipment->customer;
        $date = $this->equipment->next_calibration_date?->format('d/m/Y') ?? 'N/A';

        return (new MailMessage)
            ->subject("Calibração vencendo em {$this->daysUntilExpiry} dias — {$equipName}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O equipamento **{$equipName}**"
                .($customer ? " do cliente **{$customer->name}**" : '')
                ." possui calibração com vencimento previsto para **{$date}**.")
            ->line('Recomendamos o agendamento da recalibração com antecedência para evitar descontinuidade na conformidade.')
            ->action('Ver Equipamento', url("/equipamentos/{$this->equipment->id}"))
            ->line('Em caso de dúvidas, entre em contato com nossa equipe.');
    }

    /**
     * Data for the custom database channel (App\Models\Notification).
     */
    public function toCustomDatabase(object $notifiable): array
    {
        $customer = $this->equipment->customer;

        return [
            'tenant_id' => $this->tenantId ?? $this->equipment->tenant_id,
            'type' => 'calibration_expiring',
            'title' => 'Calibração Vencendo',
            'message' => "A calibração do equipamento {$this->equipment->serial_number}"
                .($customer ? " do cliente {$customer->name}" : '')
                ." vence em {$this->daysUntilExpiry} dias.",
            'data' => [
                'equipment_id' => $this->equipment->id,
                'customer_id' => $customer?->id,
                'days_until_expiry' => $this->daysUntilExpiry,
            ],
        ];
    }

    /**
     * Data for Laravel's default database channel (kept for compatibility).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'calibration_expiry',
            'equipment_id' => $this->equipment->id,
            'equipment_name' => $this->equipment->name ?? $this->equipment->serial_number,
            'days_until_expiry' => $this->daysUntilExpiry,
            'next_calibration_date' => $this->equipment->next_calibration_date?->toDateString(),
        ];
    }
}
