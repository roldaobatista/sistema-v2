<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * sec-reverb-cors-wildcard (Camada 1 r4 Batch C — S3)
 *
 * `config/reverb.php` não deve usar `*` como default de `allowed_origins`.
 * Em produção, configuração vazia sem fallback é fail-closed.
 *
 * Decisão arquitetural: §14.33 de `docs/TECHNICAL-DECISIONS.md`.
 */
class ReverbCorsConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolar mutações de config por teste.
        $this->app['config']->set('reverb.apps.apps', config('reverb.apps.apps'));
    }

    public function test_allowed_origins_is_empty_when_env_not_set_and_not_production(): void
    {
        // Em testing/dev, vazio-como-fallback é aceitável (o middleware faz o gate).
        // O crítico é que o default NÃO seja '*'.
        $origins = config('reverb.apps.apps.0.allowed_origins');

        $this->assertIsArray($origins);
        $this->assertNotContains(
            '*',
            $origins,
            'allowed_origins default não pode conter wildcard — CSWSH risk.'
        );
    }

    public function test_allowed_origins_reads_csv_from_env(): void
    {
        // Simula envFile populado: REVERB_ALLOWED_ORIGINS="https://a.com, https://b.com"
        putenv('REVERB_ALLOWED_ORIGINS=https://a.com, https://b.com');

        $fresh = require base_path('config/reverb.php');
        $origins = $fresh['apps']['apps'][0]['allowed_origins'];

        $this->assertSame(
            ['https://a.com', 'https://b.com'],
            $origins,
            'CSV de REVERB_ALLOWED_ORIGINS deve ser parseado com trim.'
        );

        putenv('REVERB_ALLOWED_ORIGINS');
    }

    public function test_allowed_origins_is_empty_array_when_env_empty(): void
    {
        putenv('REVERB_ALLOWED_ORIGINS=');

        $fresh = require base_path('config/reverb.php');
        $origins = $fresh['apps']['apps'][0]['allowed_origins'];

        $this->assertSame(
            [],
            $origins,
            'env vazia deve produzir array vazio (fail-closed), nunca wildcard.'
        );

        putenv('REVERB_ALLOWED_ORIGINS');
    }
}
