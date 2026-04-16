<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\DTO\PaymentChargeDTO;
use App\Services\Payment\PaymentResult;

/**
 * Contract for payment gateway providers (Asaas, Stripe, etc.).
 */
interface PaymentGatewayInterface
{
    /**
     * Create a PIX charge.
     */
    public function createPixCharge(PaymentChargeDTO $data): PaymentResult;

    /**
     * Create a Boleto charge.
     */
    public function createBoletoCharge(PaymentChargeDTO $data): PaymentResult;

    /**
     * Check payment status by external ID.
     */
    public function checkPaymentStatus(string $externalId): PaymentResult;

    /**
     * Cancel/refund a payment.
     */
    public function cancelPayment(string $externalId): PaymentResult;
}
