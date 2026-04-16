<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Nao autenticado.'], 401);
        }

        $tenantId = $this->resolveTenantFromToken($user);

        if (! $tenantId) {
            $tenantId = (int) ($user->current_tenant_id ?? $user->tenant_id ?? 0);
        }

        $isMeOrMyTenants = $this->isTenantSelectionSafeEndpoint($request);

        if ($tenantId <= 0) {
            if ($isMeOrMyTenants) {
                app()->instance('current_tenant_id', 0);

                return $next($request);
            }

            return response()->json(['message' => 'Nenhuma empresa selecionada.'], 403);
        }

        if (! $user->hasTenantAccess($tenantId)) {
            return response()->json(['message' => 'Acesso negado a esta empresa.'], 403);
        }

        $tenantStatus = Cache::remember(
            "tenant_status_{$tenantId}",
            300,
            fn () => Tenant::whereKey($tenantId)->value('status')
        );

        if ($tenantStatus === null) {
            if ($isMeOrMyTenants) {
                app()->instance('current_tenant_id', 0);

                return $next($request);
            }

            return response()->json(['message' => 'Empresa selecionada nao encontrada.'], 403);
        }

        $statusStr = $tenantStatus instanceof TenantStatus
            ? $tenantStatus->value
            : (string) $tenantStatus;
        $isInactive = $statusStr === Tenant::STATUS_INACTIVE;
        if (
            $isInactive
            && ! $this->isTenantSwitchEndpoint($request)
            && ! $this->isTenantSelectionSafeEndpoint($request)
        ) {
            return response()->json([
                'message' => 'A empresa atual esta inativa. Selecione outra empresa.',
                'tenant_inactive' => true,
            ], 403);
        }

        app()->instance('current_tenant_id', $tenantId);

        if (! $this->isTenantSwitchEndpoint($request)) {
            $request->merge(['tenant_id' => $tenantId]);
        }

        setPermissionsTeamId($tenantId);

        return $next($request);
    }

    private function isTenantSelectionSafeEndpoint(Request $request): bool
    {
        return $request->is('api/v1/me')
            || $request->is('api/v1/auth/user')
            || $request->is('api/v1/my-tenants')
            || $request->is('api/v1/my_tenants')
            || $this->isTenantSwitchEndpoint($request)
            || $request->is('api/v1/logout')
            || $request->is('api/v1/auth/logout');
    }

    private function isTenantSwitchEndpoint(Request $request): bool
    {
        return $request->is('api/v1/switch-tenant')
            || $request->is('api/v1/tenant/switch');
    }

    private function resolveTenantFromToken($user): ?int
    {
        $token = $user->currentAccessToken();
        if (! $token || empty($token->abilities)) {
            return null;
        }

        foreach ($token->abilities as $ability) {
            if (! str_starts_with($ability, 'tenant:')) {
                continue;
            }

            $tenantId = (int) substr($ability, 7);

            return $tenantId > 0 ? $tenantId : null;
        }

        return null;
    }
}
