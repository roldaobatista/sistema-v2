<?php

namespace App\Support;

class UrlSecurity
{
    private const DNS_RECORD_TYPES = DNS_A | DNS_AAAA;

    /**
     * Verifica se uma URL é segura para acesso externo, evitando SSRF.
     * Valida se o host não resolve para IPs privados ou reservados.
     */
    public static function isSafeUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? null;

        if (! $host) {
            return false;
        }

        // Se for um endereço IP direto, valida-o
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isSafeIp($host);
        }

        $ips = self::resolveHostIps($host);
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! self::isSafeIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica se um IP não pertence a faixas privadas ou reservadas.
     */
    private static function isSafeIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Resolve registros IPv4 e IPv6 para evitar bypass de SSRF via AAAA.
     *
     * @return list<string>
     */
    private static function resolveHostIps(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, self::DNS_RECORD_TYPES);

        if (is_array($records)) {
            foreach ($records as $record) {
                foreach (['ip', 'ipv6'] as $key) {
                    $ip = $record[$key] ?? null;

                    if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        if ($ips === []) {
            $resolved = gethostbynamel($host);

            if (is_array($resolved)) {
                $ips = array_values(array_filter(
                    $resolved,
                    fn (string $ip): bool => (bool) filter_var($ip, FILTER_VALIDATE_IP)
                ));
            }
        }

        return array_values(array_unique($ips));
    }
}
