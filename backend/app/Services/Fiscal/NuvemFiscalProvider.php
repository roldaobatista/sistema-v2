<?php

namespace App\Services\Fiscal;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NuvemFiscalProvider implements FiscalProvider
{
    private const GENERIC_PROVIDER_FAILURE = 'Falha na comunicacao com o provedor fiscal durante %s.';

    private string $baseUrl;

    private string $clientId;

    private string $clientSecret;

    public function __construct()
    {
        $configuredBaseUrl = config('services.nuvemfiscal.url', 'https://api.nuvemfiscal.com.br');
        $configuredClientId = config('services.nuvemfiscal.client_id', '');
        $configuredClientSecret = config('services.nuvemfiscal.client_secret', '');

        $this->baseUrl = rtrim((string) ($configuredBaseUrl ?? 'https://api.nuvemfiscal.com.br'), '/');
        $this->clientId = (string) ($configuredClientId ?? '');
        $this->clientSecret = (string) ($configuredClientSecret ?? '');
    }

    public function emitirNFe(array $data): FiscalResult
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->baseUrl}/nfe", $data);

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'provider_id' => $body['id'] ?? null,
                    'access_key' => $body['chave_acesso'] ?? null,
                    'number' => (string) ($body['numero'] ?? ''),
                    'series' => (string) ($body['serie'] ?? ''),
                    'status' => $this->mapStatus($body['status'] ?? ''),
                    'pdf_url' => $body['url_danfe'] ?? null,
                    'xml_url' => $body['url_xml'] ?? null,
                    'raw' => $body,
                ]);
            }

            Log::error('NuvemFiscal NF-e emission failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return $this->mapProviderError('emissao de NF-e', 'Erro ao emitir NF-e', $response);
        } catch (\Exception $e) {
            Log::error('NuvemFiscal NF-e exception', ['error' => $e->getMessage()]);

            return $this->providerExceptionResult('emissao de NF-e');
        }
    }

    public function emitirNFSe(array $data): FiscalResult
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->baseUrl}/nfse", $data);

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'provider_id' => $body['id'] ?? null,
                    'access_key' => $body['codigo_verificacao'] ?? $body['id'] ?? null,
                    'number' => (string) ($body['numero'] ?? ''),
                    'series' => '',
                    'status' => $this->mapStatus($body['status'] ?? ''),
                    'pdf_url' => $body['url_pdf'] ?? null,
                    'xml_url' => $body['url_xml'] ?? null,
                    'raw' => $body,
                ]);
            }

            Log::error('NuvemFiscal NFS-e emission failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return $this->mapProviderError('emissao de NFS-e', 'Erro ao emitir NFS-e', $response);
        } catch (\Exception $e) {
            Log::error('NuvemFiscal NFS-e exception', ['error' => $e->getMessage()]);

            return $this->providerExceptionResult('emissao de NFS-e');
        }
    }

    public function consultarStatus(string $referencia): FiscalResult
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/nfe/{$referencia}");

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'provider_id' => $body['id'] ?? null,
                    'access_key' => $referencia,
                    'status' => $this->mapStatus($body['status'] ?? ''),
                    'raw' => $body,
                ]);
            }

            return $this->mapProviderError('consulta de NF-e', 'Erro ao consultar NF-e', $response);
        } catch (\Exception $e) {
            Log::error('NuvemFiscal consultarStatus exception', ['error' => $e->getMessage()]);

            return $this->providerExceptionResult('consulta de NF-e');
        }
    }

    public function cancelar(string $referencia, string $justificativa): FiscalResult
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->baseUrl}/nfe/{$referencia}/cancelamento", [
                    'justificativa' => $justificativa,
                ]);

            if ($response->successful()) {
                return FiscalResult::ok([
                    'access_key' => $referencia,
                    'status' => 'cancelled',
                    'raw' => (array) $response->json(),
                ]);
            }

            return $this->mapProviderError('cancelamento de NF-e', 'Erro ao cancelar NF-e', $response);
        } catch (\Exception $e) {
            Log::error('NuvemFiscal cancelar exception', ['error' => $e->getMessage()]);

            return $this->providerExceptionResult('cancelamento de NF-e');
        }
    }

    public function cancelarNFSe(string $referencia, string $justificativa): FiscalResult
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->baseUrl}/nfse/{$referencia}/cancelamento", [
                    'justificativa' => $justificativa,
                ]);

            if ($response->successful()) {
                return FiscalResult::ok([
                    'reference' => $referencia,
                    'status' => 'cancelled',
                    'event_type' => 'cancellation',
                    'raw' => (array) $response->json(),
                ]);
            }

            return $this->mapProviderError('cancelamento de NFS-e', 'Erro ao cancelar NFS-e', $response);
        } catch (\Exception $e) {
            Log::error('NuvemFiscal cancelarNFSe exception', ['error' => $e->getMessage()]);

            return $this->providerExceptionResult('cancelamento de NFS-e');
        }
    }

    public function downloadPdf(string $referencia): string
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(30)
            ->get("{$this->baseUrl}/nfe/{$referencia}/pdf");

        if ($response->successful()) {
            return $response->body();
        }

        throw new \RuntimeException('Erro ao baixar PDF: '.$response->status());
    }

    public function downloadXml(string $referencia): string
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(30)
            ->get("{$this->baseUrl}/nfe/{$referencia}/xml");

        if ($response->successful()) {
            return $response->body();
        }

        throw new \RuntimeException('Erro ao baixar XML: '.$response->status());
    }

    public function inutilizar(array $data): FiscalResult
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->baseUrl}/nfe/inutilizacoes", $data);

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'provider_id' => $body['id'] ?? null,
                    'status' => 'inutilizado',
                    'raw' => $body,
                ]);
            }

            Log::error('NuvemFiscal inutilizar failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return $this->mapProviderError(
                'inutilizacao de numeracao fiscal',
                'Erro ao inutilizar',
                $response
            );
        } catch (\Exception $e) {
            Log::error('NuvemFiscal inutilizar exception', ['error' => $e->getMessage()]);

            return $this->providerExceptionResult('inutilizacao de numeracao fiscal');
        }
    }

    public function cartaCorrecao(string $referencia, string $correcao): FiscalResult
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->baseUrl}/nfe/{$referencia}/carta-correcao", [
                    'correcao' => $correcao,
                ]);

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'access_key' => $referencia,
                    'status' => 'carta_correcao_emitida',
                    'raw' => $body,
                ]);
            }

            Log::error('NuvemFiscal cartaCorrecao failed', [
                'referencia' => $referencia,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return $this->mapProviderError('carta de correcao', 'Erro ao emitir CC-e', $response);
        } catch (\Exception $e) {
            Log::error('NuvemFiscal cartaCorrecao exception', ['error' => $e->getMessage()]);

            return $this->providerExceptionResult('carta de correcao');
        }
    }

    public function consultarStatusServico(string $uf): FiscalResult
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/nfe/sefaz/status/{$uf}");

            if ($response->successful()) {
                $body = (array) $response->json();

                return FiscalResult::ok([
                    'uf' => $uf,
                    'status' => $body['status'] ?? 'unknown',
                    'raw' => $body,
                ]);
            }

            Log::warning('NuvemFiscal consultarStatusServico failed', [
                'uf' => $uf,
                'status' => $response->status(),
            ]);

            return $this->mapProviderError(
                'consulta de status SEFAZ',
                'Erro ao consultar status SEFAZ',
                $response
            );
        } catch (\Exception $e) {
            Log::error('NuvemFiscal consultarStatusServico exception', ['error' => $e->getMessage()]);

            return $this->providerExceptionResult('consulta de status SEFAZ');
        }
    }

    private function getAccessToken(): string
    {
        return Cache::remember('nuvemfiscal_token', 3000, function () {
            $response = Http::asForm()->post("{$this->baseUrl}/oauth/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'empresa nfe nfse cep',
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('NuvemFiscal OAuth failed: '.$response->body());
            }

            return (string) $response->json('access_token');
        });
    }

    private function mapStatus(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'autorizada', 'autorizado', 'authorized' => 'authorized',
            'cancelada', 'cancelado', 'cancelled' => 'cancelled',
            'rejeitada', 'rejeitado', 'rejected' => 'rejected',
            'processando', 'processando_autorizacao', 'processing' => 'processing',
            'pendente', 'pending' => 'pending',
            default => 'pending',
        };
    }

    private function mapProviderError(
        string $context,
        string $friendlyPrefix,
        Response $response
    ): FiscalResult {
        $body = $response->json();
        $message = is_array($body)
            ? ($body['mensagem'] ?? $body['message'] ?? ($body['erros'][0]['mensagem'] ?? null))
            : null;

        return FiscalResult::fail(
            $message ? "{$friendlyPrefix}: {$message}" : $this->genericProviderFailure($context),
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
}
