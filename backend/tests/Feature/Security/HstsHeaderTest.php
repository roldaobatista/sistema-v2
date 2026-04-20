<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Re-auditoria Camada 1 r3 — sec-02.
 *
 * HSTS deve ser emitido INCONDICIONALMENTE em produção (mesmo atrás de
 * proxy reverso quando `$request->isSecure()` retorna false), com a
 * diretiva `preload` e `max-age` elevado (>= 2 anos / 63072000 s).
 *
 * Em testing/local, mantido condicional a `isSecure()` para não poluir
 * testes HTTP simples.
 */
class HstsHeaderTest extends TestCase
{
    public function test_hsts_emitted_in_production_even_without_secure_request(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('/up');

        $header = $response->headers->get('Strict-Transport-Security');
        $this->assertNotNull($header, 'HSTS deve ser emitido em produção mesmo atrás de proxy');
        $this->assertStringContainsString('max-age=63072000', $header);
        $this->assertStringContainsString('includeSubDomains', $header);
        $this->assertStringContainsString('preload', $header);
    }

    public function test_hsts_not_emitted_in_testing_when_request_not_secure(): void
    {
        $response = $this->get('/up');

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }
}
