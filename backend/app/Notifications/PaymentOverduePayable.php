<?php

namespace App\Notifications;

use App\Models\AccountPayable;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentOverduePayable extends Notification
{
    use Queueable;

    public function __construct(
        public AccountPayable $payable,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payable;
        $dueDate = $this->normalizeDate($p->due_date)?->format('d/m/Y') ?? (string) $p->due_date;
        $amount = number_format((float) $p->amount, 2, ',', '.');

        return (new MailMessage)
            ->subject("Conta a Pagar Vencida — R$ {$amount}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("A conta a pagar **#{$p->id}** ({$p->description}) venceu em **{$dueDate}**.")
            ->line("**Valor:** R$ {$amount}")
            ->line('**Valor pago:** R$ '.number_format((float) $p->amount_paid, 2, ',', '.'))
            ->action('Ver Financeiro', config('app.frontend_url').'/financeiro/contas-a-pagar')
            ->line('Providencie o pagamento o mais breve possível.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payable_id' => $this->payable->id,
            'description' => $this->payable->description,
            'amount' => $this->payable->amount,
            'due_date' => $this->normalizeDate($this->payable->due_date)?->toDateString()
                ?? (string) $this->payable->due_date,
        ];
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}
