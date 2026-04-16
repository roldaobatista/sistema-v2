<?php

namespace Tests\Unit;

use App\Services\Fiscal\Adapters\ExternalNFeAdapter;
use App\Services\Fiscal\FocusNFeProvider;
use App\Services\Fiscal\NuvemFiscalProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FiscalProviderErrorSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_focus_provider_hides_exception_details_from_emit_result(): void
    {
        Http::fake(fn () => throw new \RuntimeException('segredo interno do provider'));

        $result = (new FocusNFeProvider)->emitirNFe(['ref' => 'nfe-1']);

        $this->assertFalse($result->success);
        $this->assertSame(
            'Falha na comunicacao com o provedor fiscal durante emissao de NF-e.',
            $result->errorMessage
        );
        $this->assertStringNotContainsString('segredo interno', (string) $result->errorMessage);
    }

    public function test_nuvem_provider_hides_unstructured_response_body(): void
    {
        config([
            'services.nuvemfiscal.url' => 'https://api.nuvemfiscal.com.br',
            'services.nuvemfiscal.client_id' => 'client-id',
            'services.nuvemfiscal.client_secret' => 'client-secret',
        ]);

        Http::fake([
            'https://api.nuvemfiscal.com.br/oauth/token' => Http::response([
                'access_token' => 'oauth-token',
            ], 200),
            'https://api.nuvemfiscal.com.br/nfe' => Http::response('<html>trace secreto</html>', 500),
        ]);

        $result = (new NuvemFiscalProvider)->emitirNFe(['ref' => 'nfe-2']);

        $this->assertFalse($result->success);
        $this->assertSame(
            'Falha na comunicacao com o provedor fiscal durante emissao de NF-e.',
            $result->errorMessage
        );
        $this->assertStringNotContainsString('trace secreto', (string) $result->errorMessage);
    }

    public function test_external_adapter_hides_exception_details_from_consultation(): void
    {
        config([
            'services.fiscal_external.base_url' => 'https://api.externo-fiscal.test',
            'services.fiscal_external.token' => 'ext-token',
        ]);

        Http::fake(fn () => throw new \RuntimeException('falha ssl detalhada'));

        $result = (new ExternalNFeAdapter)->consultarStatus('PROTO-123');

        $this->assertFalse($result->success);
        $this->assertSame(
            'Falha na comunicacao com o provedor fiscal durante consulta de NF-e.',
            $result->errorMessage
        );
        $this->assertStringNotContainsString('ssl detalhada', (string) $result->errorMessage);
    }
}
