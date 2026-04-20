<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
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

        $this->hydrateTenantAttributes($user);

        $tenantId = $this->resolveTenantFromToken($user);

        if (! $tenantId) {
            $tenantId = $this->resolveTenantFromUser($user);
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
        $this->syncResolvedTenantToUser($user, $tenantId);

        // sec-10 / CLAUDE.md Lei 4: tenant_id jamais sai do body. O contexto de
        // tenant é o container binding `current_tenant_id`; controllers devem ler
        // via `$request->user()->current_tenant_id`. Não injetar no request body
        // para evitar precedente de "tenant_id vindo do request" em refactor futuro.

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

    private function resolveTenantFromUser(User $user): int
    {
        $currentTenantId = (int) ($this->rawUserAttribute($user, 'current_tenant_id') ?? 0);
        if ($currentTenantId > 0) {
            return $currentTenantId;
        }

        return 0;
    }

    private function hydrateTenantAttributes(User $user): void
    {
        $attributes = $user->getAttributes();
        if (array_key_exists('tenant_id', $attributes) && array_key_exists('current_tenant_id', $attributes)) {
            return;
        }

        $identifier = $user->getAuthIdentifier();
        if (! $identifier) {
            return;
        }

        $fresh = User::query()->whereKey($identifier)->first(['tenant_id', 'current_tenant_id']);
        if (! $fresh) {
            return;
        }

        $fill = [];
        foreach (['tenant_id', 'current_tenant_id'] as $key) {
            if (! array_key_exists($key, $attributes) && array_key_exists($key, $fresh->getAttributes())) {
                $fill[$key] = $fresh->getAttributes()[$key];
            }
        }

        if ($fill !== []) {
            $user->forceFill($fill);
        }
    }

    private function syncResolvedTenantToUser(User $user, int $tenantId): void
    {
        if ((int) ($this->rawUserAttribute($user, 'current_tenant_id') ?? 0) !== $tenantId) {
            $user->forceFill(['current_tenant_id' => $tenantId]);
        }
    }

    private function rawUserAttribute(User $user, string $key): mixed
    {
        $attributes = $user->getAttributes();

        return array_key_exists($key, $attributes) ? $attributes[$key] : null;
    }
}
