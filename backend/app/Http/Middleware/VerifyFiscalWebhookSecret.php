<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica o secret do webhook da API externa de NF-e.
 * A API deve enviar o mesmo valor configurado em FISCAL_WEBHOOK_SECRET.
 * Aceita somente header X-Fiscal-Webhook-Secret.
 */
class VerifyFiscalWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('fiscal.webhook_secret', config('services.fiscal_external.webhook_secret'));

        if (empty($expected)) {
            if (app()->environment('production')) {
                return response()->json(['message' => 'Fiscal webhook secret not configured'], 500);
            }

            return $next($request);
        }

        $token = $request->header('X-Fiscal-Webhook-Secret');

        if (empty($token) || ! hash_equals((string) $expected, (string) $token)) {
            return response()->json(['message' => 'Invalid fiscal webhook secret'], 403);
        }

        return $next($request);
    }
}
