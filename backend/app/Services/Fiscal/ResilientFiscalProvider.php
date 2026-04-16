<?php

namespace App\Services\Fiscal;

use App\Exceptions\CircuitBreakerException;
use App\Services\Integration\CircuitBreaker;
use Illuminate\Support\Facades\Log;

/**
 * Decorator that wraps a primary FiscalProvider with Circuit Breaker
 * and falls back to a secondary provider when the primary circuit opens.
 */
class ResilientFiscalProvider implements FiscalProvider
{
    private string $primaryName;

    private string $fallbackName;

    private int $threshold;

    private int $timeout;

    public function __construct(
        private readonly FiscalProvider $primary,
        private readonly ?FiscalProvider $fallback = null,
        string $primaryName = 'fiscal_primary',
        string $fallbackName = 'fiscal_fallback',
        int $threshold = 5,
        int $timeout = 120,
    ) {
        $this->primaryName = $primaryName;
        $this->fallbackName = $fallbackName;
        $this->threshold = $threshold;
        $this->timeout = $timeout;
    }

    public function emitirNFe(array $data): FiscalResult
    {
        return $this->executeWithFallback(
            fn (FiscalProvider $provider) => $provider->emitirNFe($data),
            'emitirNFe'
        );
    }

    public function emitirNFSe(array $data): FiscalResult
    {
        return $this->executeWithFallback(
            fn (FiscalProvider $provider) => $provider->emitirNFSe($data),
            'emitirNFSe'
        );
    }

    public function consultarStatus(string $referencia): FiscalResult
    {
        return $this->executeWithFallback(
            fn (FiscalProvider $provider) => $provider->consultarStatus($referencia),
            'consultarStatus'
        );
    }

    public function cancelar(string $referencia, string $justificativa): FiscalResult
    {
        return $this->executeWithFallback(
            fn (FiscalProvider $provider) => $provider->cancelar($referencia, $justificativa),
            'cancelar'
        );
    }

    public function cancelarNFSe(string $referencia, string $justificativa): FiscalResult
    {
        return $this->executeWithFallback(
            fn (FiscalProvider $provider) => $provider->cancelarNFSe($referencia, $justificativa),
            'cancelarNFSe'
        );
    }

    public function inutilizar(array $data): FiscalResult
    {
        return $this->executeWithFallback(
            fn (FiscalProvider $provider) => $provider->inutilizar($data),
            'inutilizar'
        );
    }

    public function cartaCorrecao(string $referencia, string $correcao): FiscalResult
    {
        return $this->executeWithFallback(
            fn (FiscalProvider $provider) => $provider->cartaCorrecao($referencia, $correcao),
            'cartaCorrecao'
        );
    }

    public function consultarStatusServico(string $uf): FiscalResult
    {
        return $this->executeWithFallback(
            fn (FiscalProvider $provider) => $provider->consultarStatusServico($uf),
            'consultarStatusServico'
        );
    }

    public function downloadPdf(string $referencia): string
    {
        return $this->executeWithFallback(
            fn (FiscalProvider $provider) => $provider->downloadPdf($referencia),
            'downloadPdf'
        );
    }

    public function downloadXml(string $referencia): string
    {
        return $this->executeWithFallback(
            fn (FiscalProvider $provider) => $provider->downloadXml($referencia),
            'downloadXml'
        );
    }

    /**
     * Execute the operation on the primary provider, falling back to secondary on circuit open.
     *
     * @template T
     *
     * @param  callable(FiscalProvider): T  $operation
     * @return T
     */
    private function executeWithFallback(callable $operation, string $operationName): mixed
    {
        // Try primary provider
        try {
            return CircuitBreaker::for("fiscal_{$this->primaryName}")
                ->withThreshold($this->threshold)
                ->withTimeout($this->timeout)
                ->execute(fn () => $operation($this->primary));
        } catch (CircuitBreakerException $e) {
            Log::warning('ResilientFiscalProvider: primary circuit open, attempting fallback', [
                'primary' => $this->primaryName,
                'operation' => $operationName,
                'retry_after' => $e->getRetryAfterSeconds(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ResilientFiscalProvider: primary failed', [
                'primary' => $this->primaryName,
                'operation' => $operationName,
                'error' => $e->getMessage(),
            ]);
        }

        // Try fallback provider
        if ($this->fallback === null) {
            return FiscalResult::fail("Provider fiscal {$this->primaryName} indisponível e nenhum fallback configurado.");
        }

        try {
            $result = CircuitBreaker::for("fiscal_{$this->fallbackName}")
                ->withThreshold($this->threshold)
                ->withTimeout($this->timeout)
                ->execute(fn () => $operation($this->fallback));

            Log::info('ResilientFiscalProvider: fallback succeeded', [
                'fallback' => $this->fallbackName,
                'operation' => $operationName,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('ResilientFiscalProvider: both providers failed', [
                'primary' => $this->primaryName,
                'fallback' => $this->fallbackName,
                'operation' => $operationName,
                'error' => $e->getMessage(),
            ]);

            return FiscalResult::fail(
                "Ambos os providers fiscais ({$this->primaryName}, {$this->fallbackName}) estão indisponíveis."
            );
        }
    }
}
