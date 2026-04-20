<?php

namespace Tests\Feature\Security;

use App\Support\Config\SanctumStatefulResolver;
use Tests\TestCase;

/**
 * Re-auditoria Camada 1 r3 — sec-03.
 *
 * O default de `sanctum.stateful` NÃO deve incluir localhost/127.0.0.1
 * quando `APP_ENV=production`. Isto previne que um deploy sem
 * `SANCTUM_STATEFUL_DOMAINS` explícito aceite origens de desenvolvimento
 * como stateful (risco de CSRF/session fixation).
 *
 * Testa SanctumStatefulResolver diretamente (lógica extraída de
 * config/sanctum.php) para evitar o cache interno de env() que torna
 * mocking de APP_ENV em runtime pouco confiável entre casos.
 */
class SanctumStatefulDefaultsTest extends TestCase
{
    public function test_production_default_excludes_localhost(): void
    {
        $result = SanctumStatefulResolver::resolve('production', null, null);

        $this->assertNotContains('localhost', $result);
        $this->assertNotContains('127.0.0.1', $result);
        $this->assertNotContains('::1', $result);
        foreach ($result as $domain) {
            $this->assertStringNotContainsString('localhost:', $domain);
            $this->assertStringNotContainsString('127.0.0.1:', $domain);
        }
    }

    public function test_production_without_app_url_returns_empty(): void
    {
        $result = SanctumStatefulResolver::resolve('production', null, null);

        $this->assertSame([], $result, 'produção sem APP_URL deve retornar array vazio (força config explícita)');
    }

    public function test_production_with_app_url_uses_host(): void
    {
        $result = SanctumStatefulResolver::resolve('production', null, 'https://app.example.com');

        $this->assertSame(['app.example.com'], $result);
    }

    public function test_non_production_default_includes_localhost(): void
    {
        $result = SanctumStatefulResolver::resolve('local', null, null);

        $this->assertContains('localhost', $result);
        $this->assertContains('127.0.0.1', $result);
    }

    public function test_env_override_is_respected_in_production(): void
    {
        $result = SanctumStatefulResolver::resolve(
            'production',
            'app.example.com,admin.example.com',
            null,
        );

        $this->assertContains('app.example.com', $result);
        $this->assertContains('admin.example.com', $result);
        $this->assertCount(2, $result);
    }

    public function test_env_override_trims_and_filters_empty(): void
    {
        $result = SanctumStatefulResolver::resolve(
            'production',
            ' app.example.com , ,admin.example.com , ',
            null,
        );

        $this->assertSame(['app.example.com', 'admin.example.com'], $result);
    }
}
