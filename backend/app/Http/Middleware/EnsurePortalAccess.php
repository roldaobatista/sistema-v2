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

        // sec-11 (Re-auditoria Camada 1 r3): respeitar campos de hardening
        // ja existentes no model. Ate este fix, lockout e gate de 2FA eram
        // inertes — middleware so validava is_active/tenant/customer.

        $attributes = $user->getAttributes();
        $lockedUntil = array_key_exists('locked_until', $attributes) ? $user->locked_until : null;
        $twoFactorEnabled = array_key_exists('two_factor_enabled', $attributes) && (bool) $user->two_factor_enabled;
        $twoFactorConfirmedAt = array_key_exists('two_factor_confirmed_at', $attributes)
            ? $user->two_factor_confirmed_at
            : null;

        // Lockout temporario por tentativas de login falhas.
        if ($lockedUntil && $lockedUntil->isFuture()) {
            abort(response()->json([
                'message' => 'Conta do portal temporariamente bloqueada.',
                'locked' => true,
                'unlocks_at' => $lockedUntil->toIso8601String(),
            ], 403));
        }

        // 2FA habilitado porem nao finalizado no enrollment (sem
        // two_factor_confirmed_at) — bloquear acesso a endpoints
        // protegidos ate conclusao do setup.
        if ($twoFactorEnabled && ! $twoFactorConfirmedAt) {
            abort(response()->json([
                'message' => 'Finalize a configuracao do segundo fator para acessar o portal.',
                'require_2fa_setup' => true,
            ], 403));
        }

        app()->instance('current_tenant_id', (int) $user->tenant_id);

        return $next($request);
    }
}
