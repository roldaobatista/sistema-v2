<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates stream URLs (RTSP, HTTP, HTTPS) for cameras.
 * Unlike SecureUrl, allows private IPs since cameras are on local networks.
 */
class StreamUrl implements ValidationRule
{
    private const ALLOWED_SCHEMES = ['rtsp', 'rtsps', 'http', 'https', 'rtmp'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $parsed = parse_url($value);

        if (! $parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            $fail('A URL de stream deve conter esquema e host válidos.');

            return;
        }

        if (! in_array(strtolower($parsed['scheme']), self::ALLOWED_SCHEMES, true)) {
            $fail('O esquema da URL deve ser: '.implode(', ', self::ALLOWED_SCHEMES).'.');
        }
    }
}
