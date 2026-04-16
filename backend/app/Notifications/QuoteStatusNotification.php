<?php

namespace App\Notifications;

use App\Models\Notification;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as BaseNotification;

class QuoteStatusNotification extends BaseNotification
{
    use Queueable;

    private const STATUS_LABELS = [
        'draft' => 'Rascunho',
        'pending_internal_approval' => 'Aguard. Aprovação Interna',
        'internally_approved' => 'Aprovado Internamente',
        'sent' => 'Enviado',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
        'expired' => 'Expirado',
        'in_execution' => 'Em Execução',
        'installation_testing' => 'Instalação p/ Teste',
        'renegotiation' => 'Em Renegociação',
        'invoiced' => 'Faturado',
    ];

    private const ACTION_CONFIGS = [
        'sent' => ['icon' => 'send', 'color' => 'blue', 'subject_prefix' => 'Enviado ao cliente'],
        'approved' => ['icon' => 'check-circle', 'color' => 'green', 'subject_prefix' => 'Aprovado pelo cliente'],
        'rejected' => ['icon' => 'x-circle', 'color' => 'red', 'subject_prefix' => 'Rejeitado pelo cliente'],
        'expired' => ['icon' => 'clock', 'color' => 'amber', 'subject_prefix' => 'Expirado'],
        'invoiced' => ['icon' => 'receipt', 'color' => 'emerald', 'subject_prefix' => 'Faturado'],
        'pending_internal_approval' => ['icon' => 'shield-check', 'color' => 'amber', 'subject_prefix' => 'Aguardando sua aprovação'],
        'internally_approved' => ['icon' => 'shield-check', 'color' => 'teal', 'subject_prefix' => 'Aprovado internamente'],
    ];

    public function __construct(
        public Quote $quote,
        public string $action,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $config = self::ACTION_CONFIGS[$this->action] ?? ['subject_prefix' => 'Atualização'];
        $quote = $this->quote;
        $statusLabel = self::STATUS_LABELS[$this->action] ?? $this->action;

        $mail = (new MailMessage)
            ->subject("Orçamento #{$quote->quote_number} — {$config['subject_prefix']}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O orçamento **#{$quote->quote_number}** foi atualizado para **{$statusLabel}**.")
            ->line("**Cliente:** {$quote->customer?->name}")
            ->line('**Valor:** R$ '.number_format((float) $quote->total, 2, ',', '.'));

        if ($this->reason) {
            $mail->line("**Motivo:** {$this->reason}");
        }

        $mail->action('Ver Orçamento', config('app.frontend_url')."/orcamentos/{$quote->id}")
            ->line('Você recebeu este e-mail por ser vendedor ou responsável por este orçamento.');

        return $mail;
    }

    public function persistToDatabase(int $tenantId, int $userId): Notification
    {
        $config = self::ACTION_CONFIGS[$this->action] ?? ['icon' => 'file-text', 'color' => 'brand'];
        $statusLabel = self::STATUS_LABELS[$this->action] ?? $this->action;
        $quote = $this->quote;

        return Notification::notify(
            $tenantId,
            $userId,
            'quote_status_changed',
            "Orçamento #{$quote->quote_number} — {$statusLabel}",
            [
                'icon' => $config['icon'] ?? 'file-text',
                'color' => $config['color'] ?? 'brand',
                'link' => "/orcamentos/{$quote->id}",
                'notifiable_type' => Quote::class,
                'notifiable_id' => $quote->id,
                'message' => $this->reason
                    ? "Status: {$statusLabel}. Motivo: {$this->reason}"
                    : "Status: {$statusLabel}",
                'data' => [
                    'quote_id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'action' => $this->action,
                    'customer_name' => $quote->customer?->name,
                    'total' => $quote->total,
                ],
            ]
        );
    }
}
