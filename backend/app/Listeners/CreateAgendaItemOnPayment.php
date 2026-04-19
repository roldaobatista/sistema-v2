<?php

namespace App\Listeners;

use App\Enums\AgendaItemType;
use App\Events\PaymentReceived;
use App\Models\AgendaItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class CreateAgendaItemOnPayment implements ShouldQueue
{
    public function handle(PaymentReceived $event): void
    {
        $payment = $event->payment;

        if (! $payment) {
            return;
        }

        app()->instance('current_tenant_id', $payment->tenant_id);

        try {
            $responsavel = $payment->received_by ?? $event->accountReceivable?->created_by;

            AgendaItem::criarDeOrigem(
                model: $payment,
                tipo: AgendaItemType::FINANCEIRO,
                title: 'Pagamento recebido — R$ '.number_format((float) ($payment->amount ?? 0), 2, ',', '.'),
                responsavelId: $responsavel,
                extras: [
                    'short_description' => $payment->notes ?? 'Pagamento registrado',
                    'context' => [
                        'valor' => $payment->amount,
                        'metodo' => $payment->payment_method ?? null,
                        'link' => '/financeiro/recebimentos',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('CreateAgendaItemOnPayment failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
        }
    }
}
