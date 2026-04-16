<?php

namespace App\Http\Middleware;

use App\Models\ClientPortalUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof ClientPortalUser) {
            abort(403, 'Acesso restrito ao portal do cliente.');
        }

        if (! $user->currentAccessToken() || ! $user->tokenCan('portal:access')) {
            abort(403, 'Token sem permissao para acessar o portal.');
        }

        if (! $user->is_active) {
            $user->currentAccessToken()?->delete();
            abort(403, 'Sua conta no portal esta inativa.');
        }

        if (! $user->tenant_id || ! $user->customer_id) {
            abort(403, 'Usuario do portal sem vinculo valido.');
        }

        app()->instance('current_tenant_id', (int) $user->tenant_id);

        return $next($request);
    }
}
