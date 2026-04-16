<?php

namespace App\Http\Controllers\Api\V1\Iam;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AppliesTenantScope;
use App\Http\Requests\Iam\ToggleRolePermissionRequest;
use App\Models\AuditLog;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    use AppliesTenantScope, ResolvesCurrentTenant;

    /**
     * Lista permissões agrupadas por módulo.
     */
    public function index(Request $request): JsonResponse
    {
        $this->applyTenantScope($request);

        $search = $request->query('search');

        $groups = PermissionGroup::with(['permissions' => fn ($q) => $q->orderBy('name')])
            ->orderBy('order')
            ->when($search, function ($q) use ($search) {
                $safe = SearchSanitizer::contains($search);
                $q->where('name', 'like', $safe)
                    ->orWhereHas('permissions', fn ($p) => $p->where('name', 'like', $safe));
            })
            ->paginate(min((int) request()->input('per_page', 25), 100))
            ->through(function (PermissionGroup $group): array {
                /** @var Collection<int, Permission> $permissions */
                $permissions = $group->permissions;

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'permissions' => $permissions->map(fn (Permission $p): array => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'criticality' => $p->getAttribute('criticality'),
                    ]),
                ];
            });

        return ApiResponse::data($groups);
    }

    /**
     * Retorna a matriz de permissões (groups x roles).
     * Otimizado com eager loading para evitar N+1 queries.
     */
    public function matrix(Request $request): JsonResponse
    {
        $this->applyTenantScope($request);

        $groups = PermissionGroup::with(['permissions' => fn ($q) => $q->orderBy('name')])
            ->orderBy('order')
            ->get();

        $tenantId = $this->tenantId();
        $roles = Role::with('permissions:id,name')
            ->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            })
            ->orderBy('name')
            ->get();

        $matrix = $groups->map(function ($group) use ($roles) {
            return [
                'group' => $group->name,
                'permissions' => $group->permissions->map(function ($perm) use ($roles) {
                    return [
                        'id' => $perm->id,
                        'name' => $perm->name,
                        'criticality' => $perm->criticality,
                        'roles' => $roles->mapWithKeys(fn ($role) => [
                            $role->name => $role->permissions->contains('name', $perm->name),
                        ]),
                    ];
                }),
            ];
        });

        $matrixData = [
            'roles' => $roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'display_name' => $r->display_name ?: $r->name,
            ])->values(),
            'matrix' => $matrix,
        ];

        return response()->json(array_merge(
            $matrixData,
            ['data' => $matrixData]
        ));
    }

    /**
     * POST /permissions/toggle — ativa/desativa uma permissão para uma role.
     */
    public function toggleRolePermission(ToggleRolePermissionRequest $request): JsonResponse
    {
        $this->applyTenantScope($request);

        $validated = $request->validated();
        $tenantId = $this->tenantId();
        $role = Role::where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        })->find($validated['role_id']);

        if (! $role) {
            return ApiResponse::message('Role não encontrada neste tenant.', 404);
        }

        if ($role->tenant_id === null || (int) $role->tenant_id !== $tenantId) {
            return ApiResponse::message('Permissões de roles de sistema não podem ser alteradas. Clone a role se desejar personalizar.', 403);
        }

        if ($role->name === Role::SUPER_ADMIN) {
            return ApiResponse::message('Permissões do super_admin não podem ser alteradas.', 422);
        }

        $permission = Permission::findOrFail($validated['permission_id']);

        try {
            $hasPermission = $role->hasPermissionTo($permission);

            if ($hasPermission) {
                $role->revokePermissionTo($permission);
            } else {
                $role->givePermissionTo($permission);
            }

            app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

            AuditLog::log('updated', "Permissão '{$permission->name}' ".(! $hasPermission ? 'concedida' : 'revogada')." para role '{$role->name}'", $role);

            return ApiResponse::data(
                ['granted' => ! $hasPermission],
                200,
                [
                    'message' => ! $hasPermission
                        ? "Permissão '{$permission->name}' concedida à role '{$role->name}'."
                        : "Permissão '{$permission->name}' revogada da role '{$role->name}'.",
                ]
            );
        } catch (\Exception $e) {
            Log::error('Permission toggle failed', [
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao alterar permissão.', 500);
        }
    }
}
