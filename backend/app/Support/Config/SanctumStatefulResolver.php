<?php

namespace App\Support\Config;

/**
 * Resolve o default de `sanctum.stateful` com base no ambiente.
 *
 * Extraído de `config/sanctum.php` para permitir teste isolado sem depender
 * do cache interno de `env()` do Laravel (que torna mocking de APP_ENV
 * em runtime via putenv/$_ENV pouco confiável entre casos de teste).
 *
 * Re-auditoria Camada 1 r3 — sec-03.
 */
class SanctumStatefulResolver
{
    /**
     * @return array<int, string>
     */
    public static function resolve(mixed $appEnv, mixed $override, mixed $appUrl): array
    {
        $env = is_string($appEnv) ? $appEnv : null;
        $explicit = is_string($override) && $override !== '' ? $override : null;
        $url = is_string($appUrl) && $appUrl !== '' ? $appUrl : null;

        $raw = $explicit ?? self::defaultForEnv($env, $url);

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $domain): bool => $domain !== ''
        ));
    }

    private static function defaultForEnv(?string $appEnv, ?string $appUrl): string
    {
        $host = $appUrl !== null ? (string) parse_url($appUrl, PHP_URL_HOST) : '';

        // Em produção: fallback apenas ao host do APP_URL. Sem hosts de dev.
        // Força configuração explícita via SANCTUM_STATEFUL_DOMAINS se APP_URL
        // estiver ausente, fechando o vetor de CSRF em dev-origins aceitas.
        if ($appEnv === 'production') {
            return $host;
        }

        // Dev/local/testing: inclui localhost/127.0.0.1 por conveniência.
        return sprintf(
            '%s%s',
            'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
            $host !== '' ? ','.$host : ''
        );
    }
}
