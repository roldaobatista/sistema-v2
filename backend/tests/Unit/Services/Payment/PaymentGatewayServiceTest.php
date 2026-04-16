<?php

namespace Tests\Unit\Services\Payment;

use App\Services\Integration\CircuitBreaker;
use App\Services\Payment\AsaasPaymentProvider;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\DTO\PaymentChargeDTO;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaymentGatewayServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        CircuitBreaker::clearRegistry();
    }

    private function createDTO(array $overrides = []): PaymentChargeDTO
    {
        return PaymentChargeDTO::fromArray(array_merge([
            'amount' => 150.00,
            'description' => 'Serviço de calibração',
            'customer_name' => 'João Silva',
            'customer_document' => '12345678901',
            'customer_email' => 'joao@test.com',
            'due_date' => now()->addDays(3)->format('Y-m-d'),
        ], $overrides));
    }

    public function test_create_pix_charge_success(): void
    {
        $provider = new AsaasPaymentProvider;
        $service = new PaymentGatewayService($provider);

        $result = $service->createPixCharge($this->createDTO(), 1, 1);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->externalId);
        $this->assertStringStartsWith('PAY-PIX-', $result->externalId);
        $this->assertNotNull($result->qrCode);
        $this->assertNotNull($result->pixCopyPaste);
        $this->assertEquals('pending', $result->status);
    }

    public function test_create_boleto_charge_success(): void
    {
        $provider = new AsaasPaymentProvider;
        $service = new PaymentGatewayService($provider);

        $result = $service->createBoletoCharge($this->createDTO(), 1, 1);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->externalId);
        $this->assertStringStartsWith('PAY-BOL-', $result->externalId);
        $this->assertNotNull($result->boletoUrl);
        $this->assertNotNull($result->boletoBarcode);
        $this->assertEquals('pending', $result->status);
    }

    public function test_check_payment_status_success(): void
    {
        $provider = new AsaasPaymentProvider;
        $service = new PaymentGatewayService($provider);

        $result = $service->checkStatus('PAY-PIX-20260326-abc123');

        $this->assertTrue($result->success);
        $this->assertEquals('confirmed', $result->status);
    }

    public function test_cancel_payment_success(): void
    {
        $provider = new AsaasPaymentProvider;
        $service = new PaymentGatewayService($provider);

        $result = $service->cancel('PAY-PIX-20260326-abc123');

        $this->assertTrue($result->success);
        $this->assertEquals('cancelled', $result->status);
    }

    public function test_pix_with_circuit_breaker_open_returns_error(): void
    {
        // Trip the circuit breaker
        $cb = CircuitBreaker::for('asaas_api')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('forced'));
        } catch (\RuntimeException) {
            // expected
        }

        $provider = new AsaasPaymentProvider;
        $service = new PaymentGatewayService($provider);

        $result = $service->createPixCharge($this->createDTO(), 1, 1);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('indisponível', $result->errorMessage);
    }

    public function test_dto_from_array_creates_valid_dto(): void
    {
        $dto = PaymentChargeDTO::fromArray([
            'amount' => 250.50,
            'description' => 'Test service',
            'customer_name' => 'Maria Santos',
            'customer_document' => '98765432100',
        ]);

        $this->assertEquals(250.50, $dto->amount);
        $this->assertEquals('Test service', $dto->description);
        $this->assertEquals('Maria Santos', $dto->customerName);
        $this->assertNull($dto->customerEmail);
    }

    public function test_dto_to_array_excludes_null_values(): void
    {
        $dto = PaymentChargeDTO::fromArray([
            'amount' => 100.00,
            'description' => 'Service',
            'customer_name' => 'Test',
            'customer_document' => '111',
        ]);

        $array = $dto->toArray();

        $this->assertArrayHasKey('amount', $array);
        $this->assertArrayNotHasKey('customer_email', $array);
        $this->assertArrayNotHasKey('metadata', $array);
    }

    public function test_payment_result_ok_factory(): void
    {
        $result = PaymentResult::ok([
            'external_id' => 'EXT-001',
            'status' => 'pending',
            'qr_code' => 'QR123',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('EXT-001', $result->externalId);
        $this->assertNull($result->errorMessage);
    }

    public function test_payment_result_fail_factory(): void
    {
        $result = PaymentResult::fail('Gateway timeout', ['code' => 504]);

        $this->assertFalse($result->success);
        $this->assertEquals('Gateway timeout', $result->errorMessage);
        $this->assertEquals(['code' => 504], $result->rawResponse);
    }

    public function test_mock_provider_interface_contract(): void
    {
        $mock = \Mockery::mock(PaymentGatewayInterface::class);
        $mock->shouldReceive('createPixCharge')->once()->andReturn(PaymentResult::ok(['external_id' => 'MOCK-1']));
        $mock->shouldReceive('createBoletoCharge')->once()->andReturn(PaymentResult::ok(['external_id' => 'MOCK-2']));

        $service = new PaymentGatewayService($mock);

        $pix = $service->createPixCharge($this->createDTO(), 1, 1);
        $boleto = $service->createBoletoCharge($this->createDTO(), 1, 1);

        $this->assertTrue($pix->success);
        $this->assertTrue($boleto->success);
    }
}
