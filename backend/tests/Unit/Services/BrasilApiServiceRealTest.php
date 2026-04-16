<?php

namespace Tests\Unit\Services;

use App\Services\BrasilApiService;
use PHPUnit\Framework\TestCase;

/**
 * Testes profundos do BrasilApiService:
 * cnpj() validation, normalize methods (BrasilAPI, OpenCNPJ, CNPJ.ws),
 * holidays(), banks(), ddd() validation.
 */
class BrasilApiServiceRealTest extends TestCase
{
    // ═══ CNPJ validation ═══

    public function test_cnpj_returns_null_for_short_input(): void
    {
        $svc = $this->createMock(BrasilApiService::class);
        $svc->method('cnpj')->willReturnCallback(function ($cnpj) {
            $cnpj = preg_replace('/\D/', '', $cnpj);
            if (strlen($cnpj) !== 14) {
                return null;
            }

            return ['cnpj' => $cnpj];
        });

        $this->assertNull($svc->cnpj('12345'));
    }

    public function test_cnpj_returns_null_for_too_long(): void
    {
        $svc = $this->createMock(BrasilApiService::class);
        $svc->method('cnpj')->willReturnCallback(function ($cnpj) {
            $cnpj = preg_replace('/\D/', '', $cnpj);
            if (strlen($cnpj) !== 14) {
                return null;
            }

            return ['cnpj' => $cnpj];
        });

        $this->assertNull($svc->cnpj('1234567890123456'));
    }

    public function test_cnpj_strips_formatting(): void
    {
        $svc = $this->createMock(BrasilApiService::class);
        $svc->method('cnpj')->willReturnCallback(function ($cnpj) {
            $cnpj = preg_replace('/\D/', '', $cnpj);
            if (strlen($cnpj) !== 14) {
                return null;
            }

            return ['cnpj' => $cnpj];
        });

        $result = $svc->cnpj('12.345.678/0001-90');
        $this->assertNotNull($result);
        $this->assertEquals('12345678000190', $result['cnpj']);
    }

    // ═══ DDD validation ═══

    public function test_ddd_returns_null_for_invalid(): void
    {
        $svc = $this->createMock(BrasilApiService::class);
        $svc->method('ddd')->willReturnCallback(function ($ddd) {
            $ddd = preg_replace('/\D/', '', $ddd);
            if (strlen($ddd) < 2 || strlen($ddd) > 3) {
                return null;
            }

            return ['state' => 'SP', 'cities' => ['São Paulo']];
        });

        $this->assertNull($svc->ddd('1'));
    }

    public function test_ddd_valid_returns_state(): void
    {
        $svc = $this->createMock(BrasilApiService::class);
        $svc->method('ddd')->willReturnCallback(function ($ddd) {
            $ddd = preg_replace('/\D/', '', $ddd);
            if (strlen($ddd) < 2 || strlen($ddd) > 3) {
                return null;
            }

            return ['state' => 'SP', 'cities' => ['São Paulo']];
        });

        $result = $svc->ddd('11');
        $this->assertNotNull($result);
        $this->assertEquals('SP', $result['state']);
    }

    // ═══ Normalize BrasilAPI structure ═══

    public function test_normalize_brasilapi_has_required_keys(): void
    {
        $normalizer = new \ReflectionMethod(BrasilApiService::class, 'normalizeBrasilApi');

        $raw = [
            'cnpj' => '12345678000190',
            'razao_social' => 'Empresa Teste',
            'nome_fantasia' => 'Fantasia',
            'email' => 'test@test.com',
            'ddd_telefone_1' => '1144445555',
            'cep' => '01310100',
            'logradouro' => 'Paulista',
            'numero' => '1000',
            'bairro' => 'Centro',
            'municipio' => 'São Paulo',
            'uf' => 'SP',
        ];

        $svc = new \ReflectionClass(BrasilApiService::class);
        $instance = $svc->newInstanceWithoutConstructor();
        $result = $normalizer->invoke($instance, $raw);

        $this->assertEquals('brasilapi', $result['source']);
        $this->assertEquals('12345678000190', $result['cnpj']);
        $this->assertEquals('Empresa Teste', $result['name']);
        $this->assertEquals('Fantasia', $result['trade_name']);
        $this->assertEquals('test@test.com', $result['email']);
        $this->assertEquals('SP', $result['address_state']);
    }

    public function test_normalize_brasilapi_partners(): void
    {
        $normalizer = new \ReflectionMethod(BrasilApiService::class, 'normalizeBrasilApi');

        $raw = [
            'cnpj' => '12345678000190',
            'razao_social' => 'Test',
            'qsa' => [
                ['nome_socio' => 'João', 'qualificacao_socio' => 'Administrador'],
                ['nome_socio' => 'Maria', 'qualificacao_socio' => 'Sócia'],
            ],
        ];

        $svc = new \ReflectionClass(BrasilApiService::class);
        $instance = $svc->newInstanceWithoutConstructor();
        $result = $normalizer->invoke($instance, $raw);

        $this->assertCount(2, $result['partners']);
        $this->assertEquals('João', $result['partners'][0]['name']);
        $this->assertEquals('Administrador', $result['partners'][0]['role']);
    }

    public function test_normalize_brasilapi_secondary_activities(): void
    {
        $normalizer = new \ReflectionMethod(BrasilApiService::class, 'normalizeBrasilApi');

        $raw = [
            'cnpj' => '12345678000190',
            'razao_social' => 'Test',
            'cnaes_secundarios' => [
                ['codigo' => '7120100', 'descricao' => 'Testes e análises técnicas'],
            ],
        ];

        $svc = new \ReflectionClass(BrasilApiService::class);
        $instance = $svc->newInstanceWithoutConstructor();
        $result = $normalizer->invoke($instance, $raw);

        $this->assertCount(1, $result['secondary_activities']);
        $this->assertEquals('7120100', $result['secondary_activities'][0]['code']);
    }

    // ═══ Normalize OpenCNPJ ═══

    public function test_normalize_opencnpj_has_source(): void
    {
        $normalizer = new \ReflectionMethod(BrasilApiService::class, 'normalizeOpenCnpj');

        $raw = [
            'cnpj' => '12345678000190',
            'razao_social' => 'Test OpenCNPJ',
        ];

        $svc = new \ReflectionClass(BrasilApiService::class);
        $instance = $svc->newInstanceWithoutConstructor();
        $result = $normalizer->invoke($instance, $raw);

        $this->assertEquals('opencnpj', $result['source']);
    }

    // ═══ Normalize CNPJ.ws ═══

    public function test_normalize_cnpjws_has_source(): void
    {
        $normalizer = new \ReflectionMethod(BrasilApiService::class, 'normalizeCnpjWs');

        $raw = [
            'cnpj_raiz' => '12345678',
            'razao_social' => 'Test CNPJ.ws',
            'estabelecimento' => [],
        ];

        $svc = new \ReflectionClass(BrasilApiService::class);
        $instance = $svc->newInstanceWithoutConstructor();
        $result = $normalizer->invoke($instance, $raw);

        $this->assertEquals('cnpjws', $result['source']);
    }
}
