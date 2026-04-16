<?php

namespace App\Listeners;

use App\Events\PaymentMade;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandlePaymentMade implements ShouldQueue
{
    public function handle(PaymentMade $event): void
    {
        $ap = $event->accountPayable;
        $payment = $event->payment;

        if ($ap->tenant_id) {
            app()->instance('current_tenant_id', $ap->tenant_id);
        }

        try {
            $paymentAmount = $payment ? (float) $payment->amount : (float) $ap->amount;
            $responsavel = $payment?->received_by ?? $ap->created_by ?? null;

            if ($responsavel) {
                Notification::notify(
                    $ap->tenant_id,
                    $responsavel,
                    'payment_made',
                    'Pagamento Realizado',
                    [
                        'message' => 'Pagamento de R$ '.number_format($paymentAmount, 2, ',', '.')." registrado para {$ap->description}.",
                        'icon' => 'arrow-up',
                        'color' => 'info',
                        'data' => ['account_payable_id' => $ap->id],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error('HandlePaymentMade failed', ['ap_id' => $ap->id, 'error' => $e->getMessage()]);
        }
    }
}
