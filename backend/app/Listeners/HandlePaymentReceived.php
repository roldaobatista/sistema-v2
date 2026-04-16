<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Models\Notification;
use App\Services\CommissionService;
use App\Traits\DispatchesPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandlePaymentReceived implements ShouldQueue
{
    use DispatchesPushNotification;

    public function __construct(
        private CommissionService $commissionService,
    ) {}

    public function handle(PaymentReceived $event): void
    {
        $ar = $event->accountReceivable;
        $payment = $event->payment;

        if ($ar->tenant_id) {
            app()->instance('current_tenant_id', $ar->tenant_id);
        }

        // 1. Liberar comissões vinculadas
        try {
            $this->commissionService->releaseByPayment($ar, $payment);
        } catch (\Throwable $e) {
            Log::error('HandlePaymentReceived: commission release failed', [
                'ar_id' => $ar->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Notificar responsável
        try {
            $paymentAmount = $payment ? (float) $payment->amount : (float) $ar->amount;
            $responsavel = $payment?->received_by ?? $ar->created_by ?? null;

            if ($responsavel) {
                Notification::notify(
                    $ar->tenant_id,
                    $responsavel,
                    'payment_received',
                    'Pagamento Recebido',
                    [
                        'message' => 'Pagamento de R$ '.number_format($paymentAmount, 2, ',', '.')." recebido para {$ar->description}.",
                        'icon' => 'dollar-sign',
                        'color' => 'success',
                        'data' => ['account_receivable_id' => $ar->id],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error('HandlePaymentReceived: notification failed', [
                'ar_id' => $ar->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 3. Recalcular health score do cliente
        try {
            if ($ar->customer_id) {
                $ar->customer?->recalculateHealthScore();
            }
        } catch (\Throwable $e) {
            Log::error('HandlePaymentReceived: health score recalculation failed', [
                'ar_id' => $ar->id,
                'customer_id' => $ar->customer_id,
                'error' => $e->getMessage(),
            ]);
        }

        // 4. Push notification para financeiro
        $pushAmount = $payment ? (float) $payment->amount : (float) $ar->amount;
        $customerName = $ar->customer?->name ?? $ar->description ?? 'Cliente';
        $this->sendPushToRole(
            $ar->tenant_id,
            'financeiro',
            'Pagamento recebido',
            'R$ '.number_format($pushAmount, 2, ',', '.')." recebido de {$customerName}",
            ['type' => 'payment.received', 'account_receivable_id' => $ar->id],
        );
    }
}
