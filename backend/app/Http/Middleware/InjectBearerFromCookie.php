<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se a rota for API e existir cookie com o token de auth mas não houver header Authorization,
 * injeta o token no header para o Sanctum validar (suporte a httpOnly cookie).
 */
class InjectBearerFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*') && ! $request->bearerToken()) {
            $cookieName = config('sanctum.cookie_name', 'auth_token');
            $token = $request->cookie($cookieName);
            if ($token && is_string($token)) {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
