<?php

namespace Tests\Unit\Services\Fiscal;

use App\Services\Fiscal\FiscalProvider;
use App\Services\Fiscal\FiscalResult;
use App\Services\Fiscal\ResilientFiscalProvider;
use App\Services\Integration\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ResilientFiscalProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        CircuitBreaker::clearRegistry();
    }

    private function mockProvider(bool $success = true, string $errorMsg = 'Provider error'): FiscalProvider
    {
        $mock = \Mockery::mock(FiscalProvider::class);

        if ($success) {
            $mock->shouldReceive('emitirNFe')->andReturn(FiscalResult::ok(['status' => 'authorized']));
            $mock->shouldReceive('emitirNFSe')->andReturn(FiscalResult::ok(['status' => 'authorized']));
            $mock->shouldReceive('consultarStatus')->andReturn(FiscalResult::ok(['status' => 'authorized']));
            $mock->shouldReceive('cancelar')->andReturn(FiscalResult::ok(['status' => 'cancelled']));
            $mock->shouldReceive('inutilizar')->andReturn(FiscalResult::ok(['status' => 'inutilizado']));
            $mock->shouldReceive('cartaCorrecao')->andReturn(FiscalResult::ok(['status' => 'carta_correcao_emitida']));
            $mock->shouldReceive('consultarStatusServico')->andReturn(FiscalResult::ok(['status' => 'online']));
            $mock->shouldReceive('downloadPdf')->andReturn('%PDF-mock');
            $mock->shouldReceive('downloadXml')->andReturn('<xml>mock</xml>');
        } else {
            $mock->shouldReceive('emitirNFe')->andThrow(new \RuntimeException($errorMsg));
            $mock->shouldReceive('emitirNFSe')->andThrow(new \RuntimeException($errorMsg));
            $mock->shouldReceive('consultarStatus')->andThrow(new \RuntimeException($errorMsg));
            $mock->shouldReceive('cancelar')->andThrow(new \RuntimeException($errorMsg));
            $mock->shouldReceive('inutilizar')->andThrow(new \RuntimeException($errorMsg));
            $mock->shouldReceive('cartaCorrecao')->andThrow(new \RuntimeException($errorMsg));
            $mock->shouldReceive('consultarStatusServico')->andThrow(new \RuntimeException($errorMsg));
            $mock->shouldReceive('downloadPdf')->andThrow(new \RuntimeException($errorMsg));
            $mock->shouldReceive('downloadXml')->andThrow(new \RuntimeException($errorMsg));
        }

        return $mock;
    }

    public function test_primary_success_returns_result(): void
    {
        $provider = new ResilientFiscalProvider(
            $this->mockProvider(true),
            $this->mockProvider(true),
            'primary_test',
            'fallback_test',
        );

        $result = $provider->emitirNFe(['test' => true]);

        $this->assertTrue($result->success);
        $this->assertEquals('authorized', $result->status);
    }

    public function test_primary_fails_fallback_succeeds(): void
    {
        $provider = new ResilientFiscalProvider(
            $this->mockProvider(false),
            $this->mockProvider(true),
            'prim_fail',
            'fb_ok',
            threshold: 1,
        );

        $result = $provider->emitirNFe(['test' => true]);

        $this->assertTrue($result->success);
    }

    public function test_both_providers_fail_returns_error(): void
    {
        $provider = new ResilientFiscalProvider(
            $this->mockProvider(false, 'Primary down'),
            $this->mockProvider(false, 'Fallback down'),
            'both_fail_p',
            'both_fail_f',
            threshold: 1,
        );

        $result = $provider->emitirNFe(['test' => true]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('indisponíveis', $result->errorMessage);
    }

    public function test_no_fallback_configured_returns_error(): void
    {
        $provider = new ResilientFiscalProvider(
            $this->mockProvider(false),
            null,
            'no_fb',
        );

        $result = $provider->emitirNFe(['test' => true]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('nenhum fallback', $result->errorMessage);
    }

    public function test_circuit_open_primary_uses_fallback(): void
    {
        // Trip the primary circuit
        $cb = CircuitBreaker::for('fiscal_trip_primary')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('forced'));
        } catch (\RuntimeException) {
            // trip expected
        }

        $provider = new ResilientFiscalProvider(
            $this->mockProvider(true),
            $this->mockProvider(true),
            'trip_primary',
            'trip_fallback',
        );

        $result = $provider->consultarStatus('REF-001');

        $this->assertTrue($result->success);
    }

    public function test_nfse_emission_with_fallback(): void
    {
        $provider = new ResilientFiscalProvider(
            $this->mockProvider(false),
            $this->mockProvider(true),
            'nfse_p',
            'nfse_f',
            threshold: 1,
        );

        $result = $provider->emitirNFSe(['test' => true]);

        $this->assertTrue($result->success);
    }

    public function test_cancelamento_with_circuit_breaker(): void
    {
        $provider = new ResilientFiscalProvider(
            $this->mockProvider(true),
            null,
            'cancel_test',
        );

        $result = $provider->cancelar('REF-001', 'Erro na emissão');

        $this->assertTrue($result->success);
    }

    public function test_status_check_with_failover(): void
    {
        $provider = new ResilientFiscalProvider(
            $this->mockProvider(false),
            $this->mockProvider(true),
            'status_p',
            'status_f',
            threshold: 1,
        );

        $result = $provider->consultarStatus('REF-002');

        $this->assertTrue($result->success);
    }

    public function test_carta_correcao_delegates_correctly(): void
    {
        $provider = new ResilientFiscalProvider(
            $this->mockProvider(true),
            null,
            'cc_test',
        );

        $result = $provider->cartaCorrecao('REF-003', 'Correção de endereço');

        $this->assertTrue($result->success);
    }

    public function test_inutilizar_delegates_correctly(): void
    {
        $provider = new ResilientFiscalProvider(
            $this->mockProvider(true),
            null,
            'inut_test',
        );

        $result = $provider->inutilizar(['serie' => '1', 'numero_inicial' => '1', 'numero_final' => '10']);

        $this->assertTrue($result->success);
    }
}
