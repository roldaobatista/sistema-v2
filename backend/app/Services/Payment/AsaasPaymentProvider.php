<?php

namespace App\Services\Payment;

use App\Exceptions\CircuitBreakerException;
use App\Models\Customer;
use App\Services\Integration\CircuitBreaker;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\DTO\PaymentChargeDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asaas payment provider implementation.
 *
 * In test environments, returns deterministic mock responses.
 * In production, calls the Asaas API (https://www.asaas.com/api).
 */
class AsaasPaymentProvider implements PaymentGatewayInterface
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $configuredBaseUrl = config('payment.asaas.api_url', config('services.asaas.url', 'https://sandbox.asaas.com/api/v3'));
        $configuredApiKey = config('payment.asaas.api_key', config('services.asaas.api_key', ''));

        $this->baseUrl = rtrim((string) ($configuredBaseUrl ?? 'https://sandbox.asaas.com/api/v3'), '/');
        $this->apiKey = (string) ($configuredApiKey ?? '');
    }

    public function createPixCharge(PaymentChargeDTO $data): PaymentResult
    {
        return $this->withCircuitBreaker(function () use ($data) {
            if ($this->isMockEnvironment()) {
                return $this->mockPixCharge($data);
            }

            return $this->createCharge($data, 'PIX');
        }, 'createPixCharge');
    }

    public function createBoletoCharge(PaymentChargeDTO $data): PaymentResult
    {
        return $this->withCircuitBreaker(function () use ($data) {
            if ($this->isMockEnvironment()) {
                return $this->mockBoletoCharge($data);
            }

            return $this->createCharge($data, 'BOLETO');
        }, 'createBoletoCharge');
    }

    public function checkPaymentStatus(string $externalId): PaymentResult
    {
        return $this->withCircuitBreaker(function () use ($externalId) {
            if ($this->isMockEnvironment()) {
                return PaymentResult::ok([
                    'external_id' => $externalId,
                    'status' => 'confirmed',
                    'raw' => ['id' => $externalId, 'status' => 'CONFIRMED'],
                ]);
            }

            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout(15)
                    ->get("{$this->baseUrl}/payments/{$externalId}");

                if ($response->successful()) {
                    $body = (array) $response->json();

                    return PaymentResult::ok([
                        'external_id' => $body['id'] ?? $externalId,
                        'status' => $this->mapStatus($body['status'] ?? ''),
                        'raw' => $body,
                    ]);
                }

                return PaymentResult::fail('Erro ao consultar pagamento: '.$response->status());
            } catch (\Exception $e) {
                return PaymentResult::fail('Falha ao consultar pagamento: '.$e->getMessage());
            }
        }, 'checkPaymentStatus');
    }

    public function cancelPayment(string $externalId): PaymentResult
    {
        return $this->withCircuitBreaker(function () use ($externalId) {
            if ($this->isMockEnvironment()) {
                return PaymentResult::ok([
                    'external_id' => $externalId,
                    'status' => 'cancelled',
                    'raw' => ['id' => $externalId, 'status' => 'CANCELLED'],
                ]);
            }

            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->delete("{$this->baseUrl}/payments/{$externalId}");

                if ($response->successful()) {
                    return PaymentResult::ok([
                        'external_id' => $externalId,
                        'status' => 'cancelled',
                        'raw' => (array) $response->json(),
                    ]);
                }

                return PaymentResult::fail('Erro ao cancelar pagamento: '.$response->status());
            } catch (\Exception $e) {
                return PaymentResult::fail('Falha ao cancelar pagamento: '.$e->getMessage());
            }
        }, 'cancelPayment');
    }

    private function createCharge(PaymentChargeDTO $data, string $billingType): PaymentResult
    {
        try {
            $payload = [
                'customer' => $this->resolveOrCreateCustomer($data),
                'billingType' => $billingType,
                'value' => $data->amount,
                'description' => $data->description,
                'dueDate' => $data->dueDate ?? now()->addDays(3)->format('Y-m-d'),
                'externalReference' => isset($data->metadata['payable_id'])
                    ? ($data->metadata['payable_type'] ?? 'AccountReceivable').':'.$data->metadata['payable_id']
                    : null,
            ];

            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/payments", array_filter($payload));

            if ($response->successful()) {
                $body = (array) $response->json();

                $result = [
                    'external_id' => $body['id'] ?? null,
                    'status' => $this->mapStatus($body['status'] ?? ''),
                    'due_date' => $body['dueDate'] ?? null,
                    'raw' => $body,
                ];

                if ($billingType === 'PIX' && isset($body['id'])) {
                    $pixData = $this->getPixQrCode($body['id']);
                    $result = array_merge($result, $pixData);
                }

                if ($billingType === 'BOLETO') {
                    $result['boleto_url'] = $body['bankSlipUrl'] ?? null;
                    $result['boleto_barcode'] = $body['nossoNumero'] ?? null;
                }

                return PaymentResult::ok($result);
            }

            $errorBody = $response->json();
            $errorMsg = is_array($errorBody) ? ($errorBody['errors'][0]['description'] ?? 'Erro desconhecido') : 'Erro desconhecido';

            return PaymentResult::fail("Erro ao criar cobrança {$billingType}: {$errorMsg}", is_array($errorBody) ? $errorBody : null);
        } catch (\Exception $e) {
            Log::error("Asaas createCharge {$billingType} exception", ['error' => $e->getMessage()]);

            return PaymentResult::fail("Falha ao criar cobrança {$billingType}: ".$e->getMessage());
        }
    }

    private function getPixQrCode(string $paymentId): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(15)
                ->get("{$this->baseUrl}/payments/{$paymentId}/pixQrCode");

            if ($response->successful()) {
                $body = (array) $response->json();

                return [
                    'qr_code' => $body['payload'] ?? null,
                    'qr_code_base64' => $body['encodedImage'] ?? null,
                    'pix_copy_paste' => $body['payload'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Asaas getPixQrCode failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    private function resolveOrCreateCustomer(PaymentChargeDTO $data): string
    {
        $document = preg_replace('/\D/', '', $data->customerDocument);

        // 1. Check local DB first
        $customer = Customer::where('document', $data->customerDocument)
            ->orWhere('document', $document)
            ->first();

        if ($customer && $customer->asaas_id) {
            return $customer->asaas_id;
        }

        // 2. Search in Asaas API by document
        $searchResponse = Http::withToken($this->apiKey)
            ->timeout(15)
            ->get("{$this->baseUrl}/customers", ['cpfCnpj' => $document]);

        if ($searchResponse->successful() && ! empty($searchResponse->json('data'))) {
            $asaasId = $searchResponse->json('data.0.id');
            if ($customer) {
                $customer->update(['asaas_id' => $asaasId]);
            }

            return $asaasId;
        }

        // 3. Create new customer in Asaas
        $createPayload = [
            'name' => $data->customerName,
            'cpfCnpj' => $document,
            'email' => $data->customerEmail,
            'mobilePhone' => $data->metadata['phone'] ?? ($customer->phone ?? null),
            'notificationDisabled' => false,
        ];

        $createResponse = Http::withToken($this->apiKey)
            ->timeout(20)
            ->post("{$this->baseUrl}/customers", $createPayload);

        if ($createResponse->successful()) {
            $asaasId = $createResponse->json('id');
            if ($customer) {
                $customer->update(['asaas_id' => $asaasId]);
            }

            return $asaasId;
        }

        $error = $createResponse->json('errors.0.description') ?? 'Erro ao resolver cliente no Asaas';

        throw new \RuntimeException("Falha na integração com Asaas: {$error}");
    }

    private function mockPixCharge(PaymentChargeDTO $data): PaymentResult
    {
        $externalId = 'PAY-PIX-'.now()->format('YmdHis').'-'.substr(md5($data->customerDocument), 0, 6);

        return PaymentResult::ok([
            'external_id' => $externalId,
            'status' => 'pending',
            'qr_code' => "00020126580014BR.GOV.BCB.PIX0136{$externalId}",
            'qr_code_base64' => base64_encode("mock-qr-{$externalId}"),
            'pix_copy_paste' => "00020126580014BR.GOV.BCB.PIX0136{$externalId}",
            'due_date' => now()->addDays(1)->format('Y-m-d'),
            'raw' => ['id' => $externalId, 'status' => 'PENDING', 'value' => $data->amount],
        ]);
    }

    private function mockBoletoCharge(PaymentChargeDTO $data): PaymentResult
    {
        $externalId = 'PAY-BOL-'.now()->format('YmdHis').'-'.substr(md5($data->customerDocument), 0, 6);

        return PaymentResult::ok([
            'external_id' => $externalId,
            'status' => 'pending',
            'boleto_url' => "https://sandbox.asaas.com/b/pdf/{$externalId}",
            'boleto_barcode' => '23793.38128 60000.000003 00000.000402 1 '.now()->addDays(3)->format('Ymd').'00000'.(int) ($data->amount * 100),
            'due_date' => $data->dueDate ?? now()->addDays(3)->format('Y-m-d'),
            'raw' => ['id' => $externalId, 'status' => 'PENDING', 'value' => $data->amount],
        ]);
    }

    private function withCircuitBreaker(callable $callback, string $operation): PaymentResult
    {
        try {
            return CircuitBreaker::for('asaas_api')
                ->withThreshold((int) config('payment.asaas.circuit_breaker.threshold', 5))
                ->withTimeout((int) config('payment.asaas.circuit_breaker.timeout', 120))
                ->execute($callback);
        } catch (CircuitBreakerException $e) {
            Log::warning('Asaas circuit breaker open', ['operation' => $operation, 'retry_after' => $e->getRetryAfterSeconds()]);

            return PaymentResult::fail("Gateway de pagamento temporariamente indisponível. Tente em {$e->getRetryAfterSeconds()} segundos.");
        }
    }

    private function mapStatus(string $asaasStatus): string
    {
        return match (strtoupper($asaasStatus)) {
            'CONFIRMED', 'RECEIVED' => 'confirmed',
            'PENDING' => 'pending',
            'OVERDUE' => 'overdue',
            'REFUNDED', 'REFUND_REQUESTED' => 'refunded',
            'CANCELLED', 'DELETED' => 'cancelled',
            default => 'pending',
        };
    }

    private function isMockEnvironment(): bool
    {
        return app()->environment('testing', 'local');
    }
}
