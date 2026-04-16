<?php

namespace App\Http\Controllers\Api\V1\Iam;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AppliesTenantScope;
use App\Http\Requests\Iam\CloneRoleRequest;
use App\Http\Requests\Iam\StoreRoleRequest;
use App\Http\Requests\Iam\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    use AppliesTenantScope, ResolvesCurrentTenant;

    /**
     * Roles de sistema protegidas contra edição/exclusão.
     */
    private const PROTECTED_ROLES = [Role::SUPER_ADMIN, Role::ADMIN];

    private const GUARD_NAME = 'web';

    public function index(Request $request): JsonResponse
    {
        $this->applyTenantScope($request);

        $tenantId = $this->tenantId();

        $roles = Role::withCount(['permissions', 'users'])
            ->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            })
            ->orderBy('name')
            ->paginate(max(1, min((int) $request->input('per_page', 25), 100)));

        $roles->getCollection()->transform(function ($role) {
            $role->label = $role->display_name ?: $role->name;
            $role->is_protected = in_array($role->name, self::PROTECTED_ROLES, true);

            return $role;
        });

        return ApiResponse::paginated($roles, resourceClass: RoleResource::class);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->applyTenantScope($request);

        $tenantId = $this->tenantId();
        $validated = $request->validated();

        try {
            $role = DB::transaction(function () use ($validated, $tenantId) {
                $role = Role::create([
                    'name' => $validated['name'],
                    'display_name' => $validated['display_name'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'guard_name' => self::GUARD_NAME,
                    'tenant_id' => $tenantId,
                ]);

                if (! empty($validated['permissions'])) {
                    $role->syncPermissions($validated['permissions']);
                }

                return $role;
            });

            $role->load('permissions:id,name');

            AuditLog::log('created', "Role {$role->name} criada", $role);

            return ApiResponse::data(new RoleResource($role), 201);
        } catch (\Exception $e) {
            Log::error('Role store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar role.', 500);
        }
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $this->applyTenantScope($request);

        $tenantId = $this->tenantId();
        abort_unless($role->tenant_id === null || (int) $role->tenant_id === $tenantId, 404);

        $role->load('permissions:id,name,group_id,criticality');

        $role->is_protected = in_array($role->name, self::PROTECTED_ROLES, true);

        return ApiResponse::data(new RoleResource($role));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->applyTenantScope($request);

        if ($role->name === Role::SUPER_ADMIN) {
            return ApiResponse::message('A role super_admin não pode ser editada.', 422);
        }

        $tenantId = $this->tenantId();

        if ($role->tenant_id === null || (int) $role->tenant_id !== $tenantId) {
            return ApiResponse::message('Acesso negado. Não é possível editar roles globais ou de outros tenants.', 403);
        }

        $validated = $request->validated();

        // Protege a role admin contra renomeação
        if (in_array($role->name, self::PROTECTED_ROLES, true) && isset($validated['name']) && $validated['name'] !== $role->name) {
            return ApiResponse::message('Roles do sistema não podem ser renomeadas.', 422);
        }

        try {
            DB::transaction(function () use ($role, $validated) {
                $updateData = [];
                if (isset($validated['name'])) {
                    $updateData['name'] = $validated['name'];
                }
                if (array_key_exists('display_name', $validated)) {
                    $updateData['display_name'] = $validated['display_name'];
                }
                if (array_key_exists('description', $validated)) {
                    $updateData['description'] = $validated['description'];
                }
                if (! empty($updateData)) {
                    $role->update($updateData);
                }

                if (isset($validated['permissions'])) {
                    $role->syncPermissions($validated['permissions']);
                }
            });

            $role->load('permissions:id,name');

            AuditLog::log('updated', "Role {$role->name} atualizada", $role);

            return ApiResponse::data(new RoleResource($role));
        } catch (\Exception $e) {
            Log::error('Role update failed', ['role_id' => $role->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar role.', 500);
        }
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->applyTenantScope($request);

        $tenantId = $this->tenantId();

        // Verificar que a role pertence ao tenant atual (não pode excluir globais)
        if ($role->tenant_id === null || (int) $role->tenant_id !== $tenantId) {
            return ApiResponse::message('Acesso negado. Não é possível excluir roles globais ou de outros tenants.', 403);
        }

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return ApiResponse::message('Roles do sistema não podem ser excluídas.', 422);
        }

        $usersCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();
        if ($usersCount > 0) {
            return ApiResponse::message('Esta role possui usuários atribuídos. Remova os usuários antes de excluí-la.', 422);
        }

        try {
            $roleName = $role->name;
            $role->delete();

            AuditLog::log('deleted', "Role {$roleName} excluída");

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('Role delete failed', ['role_id' => $role->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir role.', 500);
        }
    }

    /**
     * GET /roles/{role}/users — lista os usuários atribuídos a esta role.
     */
    public function users(Request $request, Role $role): JsonResponse
    {
        $this->applyTenantScope($request);

        $tenantId = $this->tenantId();
        abort_unless($role->tenant_id === null || (int) $role->tenant_id === $tenantId, 404);

        $users = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->whereHas('roles', fn ($q) => $q->where('roles.id', $role->id))
            ->select('id', 'name', 'email', 'is_active')
            ->orderBy('name')
            ->paginate(max(1, min((int) $request->input('per_page', 25), 100)));

        return ApiResponse::paginated($users);
    }

    /**
     * POST /roles/{role}/clone — clona uma role com todas as suas permissões.
     */
    public function clone(CloneRoleRequest $request, Role $role): JsonResponse
    {
        $this->applyTenantScope($request);

        $tenantId = $this->tenantId();

        // Verificar que a role pertence ao tenant atual
        if ($role->tenant_id !== null && (int) $role->tenant_id !== $tenantId) {
            return ApiResponse::message('Acesso negado a esta role.', 403);
        }

        $validated = $request->validated();

        try {
            $newRole = DB::transaction(function () use ($role, $validated, $tenantId) {
                $newRole = Role::create([
                    'name' => $validated['name'],
                    'display_name' => $validated['display_name'] ?? $role->display_name,
                    'description' => $role->description,
                    'guard_name' => self::GUARD_NAME,
                    'tenant_id' => $tenantId,
                ]);

                $permissionIds = $role->permissions->pluck('id')->toArray();
                if (! empty($permissionIds)) {
                    $newRole->syncPermissions($permissionIds);
                }

                return $newRole;
            });

            $newRole->load('permissions:id,name');

            AuditLog::log('created', "Role {$newRole->name} clonada de {$role->name}", $newRole);

            return ApiResponse::data(new RoleResource($newRole), 201);
        } catch (\Exception $e) {
            Log::error('Role clone failed', ['role_id' => $role->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao clonar role.', 500);
        }
    }
}
