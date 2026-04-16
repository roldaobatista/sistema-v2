<?php

namespace App\Services\Fiscal;

use App\Exceptions\CircuitBreakerException;
use App\Services\Integration\CircuitBreaker;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FocusNFeProvider implements FiscalProvider
{
    private const GENERIC_PROVIDER_FAILURE = 'Falha na comunicacao com o provedor fiscal durante %s.';

    private string $baseUrl;

    private string $token;

    public function __construct()
    {
        // Prefer dedicated fiscal.php config, fall back to services.php (legacy)
        $environment = config('fiscal.focusnfe.environment', config('services.focusnfe.environment', 'homologation'));
        $this->token = (string) config('fiscal.focusnfe.token', config('services.focusnfe.token', ''));

        $urlProduction = config('fiscal.focusnfe.url_production', config('services.focusnfe.url_production', 'https://api.focusnfe.com.br'));
        $urlHomologation = config('fiscal.focusnfe.url_homologation', config('services.focusnfe.url_homologation', 'https://homologacao.focusnfe.com.br'));

        $this->baseUrl = $environment === 'production'
            ? rtrim((string) $urlProduction, '/')
            : rtrim((string) $urlHomologation, '/');
    }

    public function emitirNFe(array $data): FiscalResult
    {
        return $this->withCircuitBreaker('emissao de NF-e', function () use ($data) {
            $ref = $data['ref'] ?? uniqid('nfe_');

            $response = $this->request()
                ->post("{$this->baseUrl}/v2/nfe?ref={$ref}", $data);

            if ($response->successful()) {
                return $this->handleNFeResponse((array) $response->json(), $ref);
            }

            $result = $this->handleError('NF-e', $response);

            throw new \RuntimeException($result->errorMessage);
        });
    }

    public function emitirNFSe(array $data): FiscalResult
    {
        return $this->withCircuitBreaker('emissao de NFS-e', function () use ($data) {
            $ref = $data['ref'] ?? uniqid('nfse_');

            $response = $this->request()
                ->post("{$this->baseUrl}/v2/nfse?ref={$ref}", $data);

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'provider_id' => $body['id'] ?? $ref,
                    'reference' => $ref,
                    'status' => $this->mapStatus($body['status'] ?? ''),
                    'raw' => $body,
                ]);
            }

            $result = $this->handleError('NFS-e', $response);

            throw new \RuntimeException($result->errorMessage);
        });
    }

    public function consultarStatus(string $referencia): FiscalResult
    {
        return $this->withCircuitBreaker('consulta de NF-e', function () use ($referencia) {
            $response = $this->request()
                ->get("{$this->baseUrl}/v2/nfe/{$referencia}");

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'reference' => $referencia,
                    'access_key' => $body['chave_nfe'] ?? null,
                    'number' => (string) ($body['numero'] ?? ''),
                    'series' => (string) ($body['serie'] ?? ''),
                    'status' => $this->mapStatus($body['status'] ?? ''),
                    'protocol_number' => $body['protocolo'] ?? null,
                    'pdf_url' => $body['caminho_danfe'] ?? null,
                    'xml_url' => $body['caminho_xml_nota_fiscal'] ?? null,
                    'raw' => $body,
                ]);
            }

            $result = $this->handleError('Consulta NF-e', $response);

            throw new \RuntimeException($result->errorMessage);
        });
    }

    public function cancelar(string $referencia, string $justificativa): FiscalResult
    {
        return $this->withCircuitBreaker('cancelamento de NF-e', function () use ($referencia, $justificativa) {
            $response = $this->request()
                ->delete("{$this->baseUrl}/v2/nfe/{$referencia}", [
                    'justificativa' => $justificativa,
                ]);

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'reference' => $referencia,
                    'status' => 'cancelled',
                    'event_type' => 'cancellation',
                    'protocol_number' => $body['protocolo'] ?? null,
                    'raw' => $body,
                ]);
            }

            $result = $this->handleError('Cancelamento', $response);

            throw new \RuntimeException($result->errorMessage);
        });
    }

    public function inutilizar(array $data): FiscalResult
    {
        return $this->withCircuitBreaker('inutilizacao de numeracao fiscal', function () use ($data) {
            $response = $this->request()
                ->post("{$this->baseUrl}/v2/nfe/inutilizacao", [
                    'cnpj' => $data['cnpj'],
                    'serie' => $data['serie'],
                    'numero_inicial' => $data['numero_inicial'],
                    'numero_final' => $data['numero_final'],
                    'justificativa' => $data['justificativa'],
                    'modelo' => $data['modelo'] ?? '55',
                ]);

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'status' => $this->mapStatus($body['status'] ?? ''),
                    'event_type' => 'inutilization',
                    'protocol_number' => $body['protocolo'] ?? null,
                    'raw' => $body,
                ]);
            }

            $result = $this->handleError('Inutilizacao', $response);

            throw new \RuntimeException($result->errorMessage);
        });
    }

    public function cartaCorrecao(string $referencia, string $correcao): FiscalResult
    {
        return $this->withCircuitBreaker('carta de correcao', function () use ($referencia, $correcao) {
            $response = $this->request()
                ->post("{$this->baseUrl}/v2/nfe/{$referencia}/carta_correcao", [
                    'correcao' => $correcao,
                ]);

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'reference' => $referencia,
                    'status' => 'authorized',
                    'event_type' => 'correction',
                    'correction_text' => $correcao,
                    'protocol_number' => $body['protocolo'] ?? null,
                    'raw' => $body,
                ]);
            }

            $result = $this->handleError('Carta de Correcao', $response);

            throw new \RuntimeException($result->errorMessage);
        });
    }

    public function consultarStatusServico(string $uf): FiscalResult
    {
        return $this->withCircuitBreaker('consulta de status SEFAZ', function () use ($uf) {
            $response = $this->request()
                ->get("{$this->baseUrl}/v2/nfe/sefaz_status", [
                    'uf' => $uf,
                ]);

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'status' => $body['status_sefaz'] ?? 'unknown',
                    'raw' => $body,
                ]);
            }

            $result = $this->handleError('Consulta status SEFAZ', $response);

            throw new \RuntimeException($result->errorMessage);
        });
    }

    public function downloadPdf(string $referencia): string
    {
        $response = $this->request()
            ->withHeaders(['Accept' => 'application/pdf'])
            ->get("{$this->baseUrl}/v2/nfe/{$referencia}.pdf");

        if ($response->successful()) {
            return $response->body();
        }

        throw new \RuntimeException('Erro ao baixar PDF: '.$response->status());
    }

    public function downloadXml(string $referencia): string
    {
        $response = $this->request()
            ->withHeaders(['Accept' => 'application/xml'])
            ->get("{$this->baseUrl}/v2/nfe/{$referencia}.xml");

        if ($response->successful()) {
            return $response->body();
        }

        throw new \RuntimeException('Erro ao baixar XML: '.$response->status());
    }

    public function consultarNFSe(string $referencia): FiscalResult
    {
        return $this->withCircuitBreaker('consulta de NFS-e', function () use ($referencia) {
            $response = $this->request()
                ->get("{$this->baseUrl}/v2/nfse/{$referencia}");

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'reference' => $referencia,
                    'number' => (string) ($body['numero'] ?? ''),
                    'verification_code' => $body['codigo_verificacao'] ?? null,
                    'status' => $this->mapStatus($body['status'] ?? ''),
                    'pdf_url' => $body['caminho_pdf'] ?? null,
                    'xml_url' => $body['caminho_xml'] ?? null,
                    'raw' => $body,
                ]);
            }

            $result = $this->handleError('Consulta NFS-e', $response);

            throw new \RuntimeException($result->errorMessage);
        });
    }

    public function cancelarNFSe(string $referencia, string $justificativa): FiscalResult
    {
        return $this->withCircuitBreaker('cancelamento de NFS-e', function () use ($referencia, $justificativa) {
            $response = $this->request()
                ->delete("{$this->baseUrl}/v2/nfse/{$referencia}", [
                    'justificativa' => $justificativa,
                ]);

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'reference' => $referencia,
                    'status' => 'cancelled',
                    'event_type' => 'cancellation',
                    'raw' => $body,
                ]);
            }

            $result = $this->handleError('Cancelamento NFS-e', $response);

            throw new \RuntimeException($result->errorMessage);
        });
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth($this->token, '')
            ->timeout(30)
            ->acceptJson()
            ->retry(2, 500, function (\Throwable $exception, PendingRequest $request, ?string $responseBody = null): bool {
                if ($exception instanceof RequestException) {
                    $status = $exception->response->status();

                    return in_array($status, [429, 500, 502, 503, 504]);
                }

                return false;
            }, throw: false);
    }

    /**
     * Check if the FocusNFe circuit is open (useful for contingency logic).
     */
    public function isCircuitOpen(): bool
    {
        return CircuitBreaker::for('focusnfe')->isOpen();
    }

    /**
     * Execute a fiscal operation through the circuit breaker.
     * Returns FiscalResult::fail() when circuit is open.
     *
     * @param  string  $context  Description of the operation (for error messages)
     * @param  callable(): FiscalResult  $operation
     */
    private function withCircuitBreaker(string $context, callable $operation): FiscalResult
    {
        try {
            return CircuitBreaker::for('focusnfe')
                ->withThreshold(7)
                ->withTimeout(180)
                ->execute($operation);
        } catch (CircuitBreakerException $e) {
            Log::warning('FocusNFe: circuit breaker open', [
                'context' => $context,
                'retry_after' => $e->getRetryAfterSeconds(),
            ]);

            return FiscalResult::fail(
                "Provedor fiscal temporariamente indisponível durante {$context}. Tente novamente em {$e->getRetryAfterSeconds()}s."
            );
        } catch (\Throwable $e) {
            Log::error('FocusNFe provider exception', [
                'context' => $context,
                'error' => $e->getMessage(),
            ]);

            return $this->providerExceptionResult($context);
        }
    }

    private function handleNFeResponse(array $body, string $ref): FiscalResult
    {
        $status = $this->mapStatus($body['status'] ?? '');

        if ($status === 'processing') {
            return FiscalResult::ok([
                'provider_id' => $ref,
                'reference' => $ref,
                'status' => 'processing',
                'raw' => $body,
            ]);
        }

        return FiscalResult::ok([
            'provider_id' => $ref,
            'reference' => $ref,
            'access_key' => $body['chave_nfe'] ?? null,
            'number' => (string) ($body['numero'] ?? ''),
            'series' => (string) ($body['serie'] ?? ''),
            'status' => $status,
            'protocol_number' => $body['protocolo'] ?? null,
            'pdf_url' => $body['caminho_danfe'] ?? null,
            'xml_url' => $body['caminho_xml_nota_fiscal'] ?? null,
            'raw' => $body,
        ]);
    }

    private function handleError(string $context, Response $response): FiscalResult
    {
        $body = $response->json();
        $message = is_array($body)
            ? ($body['mensagem'] ?? ($body['erros'][0]['mensagem'] ?? null))
            : null;

        Log::error("FocusNFe {$context} failed", [
            'status' => $response->status(),
            'response' => $body,
        ]);

        return FiscalResult::fail(
            $message ? "Erro {$context}: {$message}" : $this->genericProviderFailure($context),
            is_array($body) ? $body : null
        );
    }

    private function providerExceptionResult(string $context): FiscalResult
    {
        return FiscalResult::fail($this->genericProviderFailure($context));
    }

    private function genericProviderFailure(string $context): string
    {
        return sprintf(self::GENERIC_PROVIDER_FAILURE, $context);
    }

    private function mapStatus(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'autorizado', 'autorizada', 'authorized' => 'authorized',
            'cancelado', 'cancelada', 'cancelled' => 'cancelled',
            'erro_autorizacao', 'rejeitado', 'rejeitada', 'rejected' => 'rejected',
            'processando_autorizacao', 'processing' => 'processing',
            default => 'pending',
        };
    }
}
