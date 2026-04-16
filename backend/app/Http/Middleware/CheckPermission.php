<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Permite manter rotas canônicas com permissões granulares sem quebrar
     * usuários legados que ainda possuem apenas a permissão agregada antiga.
     *
     * @var array<string, array<int, string>>
     */
    private const PERMISSION_ALIASES = [
        'estoque.used_stock.view' => ['estoque.movement.view'],
        'estoque.used_stock.report' => ['estoque.movement.create'],
        'estoque.used_stock.confirm' => ['estoque.movement.create'],
        'reports.crm_report.view' => ['crm.deal.view'],
    ];

    public function handle(Request $request, Closure $next, string $permissionExpression): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Nao autenticado.'], 401);
        }

        $tenantId = $this->resolveTenantId($request, $user);
        if ($tenantId !== null && method_exists($user, 'hasTenantAccess') && ! $user->hasTenantAccess($tenantId)) {
            return response()->json(['message' => 'Acesso negado a esta empresa.'], 403);
        }

        if ($tenantId !== null) {
            app()->instance('current_tenant_id', $tenantId);
        }

        setPermissionsTeamId($tenantId);

        if ($user->hasRole(Role::SUPER_ADMIN)) {
            return $next($request);
        }

        $permissions = array_values(array_filter(array_map(
            static fn (string $item) => trim($item),
            explode('|', $permissionExpression)
        )));

        if (empty($permissions)) {
            return response()->json([
                'message' => 'Acesso negado. Permissao nao configurada.',
            ], 403);
        }

        $guardName = Guard::getDefaultName($user);

        $permissionClass = app(PermissionRegistrar::class)->getPermissionClass();
        $permissionCandidates = $this->expandPermissionsWithAliases($permissions);

        $configuredPermissions = $permissionClass::query()
            ->where('guard_name', $guardName)
            ->whereIn('name', $permissionCandidates)
            ->pluck('name')
            ->all();

        $missingPermissions = array_values(array_diff($permissions, $configuredPermissions));

        if (empty($configuredPermissions)) {
            return response()->json([
                'message' => 'Acesso negado. Permissao nao configurada: '.implode(' | ', $permissions),
                'missing_permissions' => $missingPermissions,
            ], 403);
        }

        $deniedList = method_exists($user, 'getDeniedPermissionsList')
            ? $user->getDeniedPermissionsList()
            : [];

        foreach ($permissions as $permission) {
            foreach ($this->resolvePermissionCandidates($permission) as $candidate) {
                if (! in_array($candidate, $configuredPermissions, true)) {
                    continue;
                }

                if (in_array($candidate, $deniedList, true)) {
                    continue;
                }

                $has = $user->hasPermissionTo($candidate, $guardName);

                if ($has) {
                    return $next($request);
                }
            }
        }

        return response()->json([
            'message' => 'Acesso negado. Permissao necessaria: '.implode(' | ', $permissions),
            ...(! empty($missingPermissions) ? ['missing_permissions' => $missingPermissions] : []),
        ], 403);
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    private function expandPermissionsWithAliases(array $permissions): array
    {
        $expanded = [];

        foreach ($permissions as $permission) {
            foreach ($this->resolvePermissionCandidates($permission) as $candidate) {
                $expanded[] = $candidate;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @return array<int, string>
     */
    private function resolvePermissionCandidates(string $permission): array
    {
        return array_values(array_unique([
            $permission,
            ...(self::PERMISSION_ALIASES[$permission] ?? []),
        ]));
    }

    private function resolveTenantId(Request $request, $user): ?int
    {
        if (app()->bound('current_tenant_id')) {
            $tenantId = (int) app('current_tenant_id');

            return $tenantId > 0 ? $tenantId : null;
        }

        $tokenTenantId = $this->resolveTenantFromToken($user);
        if ($tokenTenantId !== null) {
            return $tokenTenantId;
        }

        $fallbackTenantId = (int) ($user->current_tenant_id ?? $user->tenant_id ?? 0);

        return $fallbackTenantId > 0 ? $fallbackTenantId : null;
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
