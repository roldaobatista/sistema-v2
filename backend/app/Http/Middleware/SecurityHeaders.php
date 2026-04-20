<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');

        // sec-csp-unsafe-inline-eval (Camada 1 r4 Batch B):
        // Em produção, remover 'unsafe-inline' e 'unsafe-eval' de script-src
        // (OWASP ASVS V14.4.3 — CSP sem escape de script).
        // style-src mantém 'unsafe-inline' por exceção documentada em
        // docs/TECHNICAL-DECISIONS.md §14.22 (Tailwind/Radix geram styles
        // inline dinâmicos; migrar para nonce é escopo de Camada 2).
        // Dev/test mantêm 'unsafe-inline'/'unsafe-eval' em script-src para
        // compatibilidade com Vite HMR (eval em runtime).
        if (app()->environment('production')) {
            $scriptSrc = "script-src 'self'";
        } else {
            $scriptSrc = "script-src 'self' 'unsafe-inline' 'unsafe-eval'";
        }

        $csp = implode('; ', [
            "default-src 'self'",
            $scriptSrc,
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self' ".config('app.url', '').' https://viacep.com.br',
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Re-auditoria Camada 1 r3 — sec-02:
        // Em produção, emitir HSTS SEMPRE (atrás de proxy reverso `$request->isSecure()`
        // pode retornar false mesmo com TLS real). max-age >= 2 anos + preload para
        // elegibilidade à preload list dos navegadores.
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        } elseif ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        return $response;
    }
}
