<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a payment webhook callback is processed successfully.
 * Used for external gateway confirmations (Pix, Boleto, etc.).
 */
class PaymentWebhookProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Payment $payment,
        public string $event,
    ) {}
}
