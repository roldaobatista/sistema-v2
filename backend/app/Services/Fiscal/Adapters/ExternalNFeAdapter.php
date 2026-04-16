<?php

namespace App\Services\Fiscal\Adapters;

use App\Services\Fiscal\Contracts\FiscalGatewayInterface;
use App\Services\Fiscal\DTO\NFeDTO;
use App\Services\Fiscal\FiscalResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalNFeAdapter implements FiscalGatewayInterface
{
    private const GENERIC_PROVIDER_FAILURE = 'Falha na comunicacao com o provedor fiscal durante %s.';

    private string $baseUrl;

    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.fiscal_external.base_url', 'https://api.exemplo.com.br'), '/');
        $this->token = (string) config('services.fiscal_external.token', '');
    }

    public function emitirNFe(NFeDTO $data): FiscalResult
    {
        try {
            $response = $this->request()
                ->post("{$this->baseUrl}/v2/nfe", $data->toArray());

            if ($response->successful()) {
                return $this->mapEmissionResponseToResult((array) $response->json(), $data->reference);
            }

            return $this->mapErrorToResult('emissao de NF-e', $response, 'Erro ao emitir NF-e');
        } catch (\Throwable $e) {
            Log::error('ExternalNFeAdapter::emitirNFe exception', ['error' => $e->getMessage()]);

            return $this->providerExceptionResult('emissao de NF-e');
        }
    }

    public function consultarStatus(string $protocolo): FiscalResult
    {
        try {
            $response = $this->request()
                ->get("{$this->baseUrl}/v2/nfe/".urlencode($protocolo));

            if ($response->successful()) {
                return $this->mapStatusResponseToResult((array) $response->json(), $protocolo);
            }

            return $this->mapErrorToResult('consulta de NF-e', $response, 'Erro ao consultar NF-e');
        } catch (\Throwable $e) {
            Log::error('ExternalNFeAdapter::consultarStatus exception', ['error' => $e->getMessage()]);

            return $this->providerExceptionResult('consulta de NF-e');
        }
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->token)
            ->timeout(30)
            ->acceptJson();
    }

    private function mapEmissionResponseToResult(array $body, string $reference): FiscalResult
    {
        $status = $this->mapStatus($body['status'] ?? '');

        return FiscalResult::ok([
            'provider_id' => $body['id'] ?? $reference,
            'reference' => $reference,
            'access_key' => $body['chave_nfe'] ?? $body['chave'] ?? null,
            'number' => isset($body['numero']) ? (string) $body['numero'] : null,
            'series' => isset($body['serie']) ? (string) $body['serie'] : null,
            'status' => $status,
            'protocol_number' => $body['protocolo'] ?? null,
            'pdf_url' => $body['caminho_danfe'] ?? $body['pdf_url'] ?? null,
            'xml_url' => $body['caminho_xml_nota_fiscal'] ?? $body['xml_url'] ?? null,
            'raw' => $body,
        ]);
    }

    private function mapStatusResponseToResult(array $body, string $protocolo): FiscalResult
    {
        $status = $this->mapStatus($body['status'] ?? '');

        return FiscalResult::ok([
            'reference' => $protocolo,
            'access_key' => $body['chave_nfe'] ?? $body['chave'] ?? null,
            'number' => isset($body['numero']) ? (string) $body['numero'] : null,
            'series' => isset($body['serie']) ? (string) $body['serie'] : null,
            'status' => $status,
            'protocol_number' => $body['protocolo'] ?? null,
            'pdf_url' => $body['caminho_danfe'] ?? $body['pdf_url'] ?? null,
            'xml_url' => $body['caminho_xml_nota_fiscal'] ?? $body['xml_url'] ?? null,
            'raw' => $body,
        ]);
    }

    private function mapErrorToResult(
        string $context,
        Response $response,
        ?string $friendlyPrefix = null
    ): FiscalResult {
        $body = $response->json();
        $message = is_array($body)
            ? ($body['mensagem'] ?? $body['message'] ?? ($body['erros'][0]['mensagem'] ?? null))
            : null;

        Log::error("ExternalNFeAdapter {$context} failed", [
            'status' => $response->status(),
            'response' => $body,
        ]);

        return FiscalResult::fail(
            $message
                ? (($friendlyPrefix ?? "Erro {$context}").": {$message}")
                : $this->genericProviderFailure($context),
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
