<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica assinatura do webhook via token compartilhado.
 * Aceita: header X-Webhook-Secret ou query ?token=
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.webhook_secret');

        // When no secret is configured, block in production to prevent unauthenticated access
        if (! $expected) {
            if (app()->environment('production')) {
                return response()->json(['message' => 'Webhook secret not configured'], 500);
            }

            return $next($request);
        }

        // Aceita apenas via header (nunca via query string para evitar vazamento em logs)
        $token = $request->header('X-Webhook-Secret');

        if (! $token || ! hash_equals($expected, $token)) {
            return response()->json(['message' => 'Assinatura de webhook inválida'], 403);
        }

        return $next($request);
    }
}
