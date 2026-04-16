<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\DTO\PaymentChargeDTO;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrator that selects the correct payment gateway provider
 * based on tenant configuration and delegates operations.
 */
class PaymentGatewayService
{
    public function __construct(private readonly PaymentGatewayInterface $provider) {}

    public function createPixCharge(PaymentChargeDTO $data, int $tenantId, int $userId): PaymentResult
    {
        Log::info('PaymentGatewayService: creating PIX charge', [
            'tenant_id' => $tenantId,
            'amount' => $data->amount,
        ]);

        $result = $this->provider->createPixCharge($data);

        if ($result->success) {
            Log::info('PaymentGatewayService: PIX charge created', [
                'external_id' => $result->externalId,
                'tenant_id' => $tenantId,
            ]);
        }

        return $result;
    }

    public function createBoletoCharge(PaymentChargeDTO $data, int $tenantId, int $userId): PaymentResult
    {
        Log::info('PaymentGatewayService: creating Boleto charge', [
            'tenant_id' => $tenantId,
            'amount' => $data->amount,
        ]);

        $result = $this->provider->createBoletoCharge($data);

        if ($result->success) {
            Log::info('PaymentGatewayService: Boleto charge created', [
                'external_id' => $result->externalId,
                'tenant_id' => $tenantId,
            ]);
        }

        return $result;
    }

    public function checkStatus(string $externalId): PaymentResult
    {
        return $this->provider->checkPaymentStatus($externalId);
    }

    public function cancel(string $externalId): PaymentResult
    {
        return $this->provider->cancelPayment($externalId);
    }
}
