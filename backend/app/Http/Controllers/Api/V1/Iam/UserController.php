<?php

namespace App\Http\Controllers\Api\V1\Iam;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AppliesTenantScope;
use App\Http\Requests\Iam\AssignRolesRequest;
use App\Http\Requests\Iam\BulkToggleActiveRequest;
use App\Http\Requests\Iam\GrantPermissionsRequest;
use App\Http\Requests\Iam\ResetPasswordRequest;
use App\Http\Requests\Iam\RevokePermissionsRequest;
use App\Http\Requests\Iam\StoreUserRequest;
use App\Http\Requests\Iam\SyncDeniedPermissionsRequest;
use App\Http\Requests\Iam\SyncDirectPermissionsRequest;
use App\Http\Requests\Iam\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    use AppliesTenantScope, ResolvesCurrentTenant;

    private function tenantIdForRequest(Request $request): int
    {
        $this->applyTenantScope($request);

        return $this->tenantId();
    }

    private function resolveTenantUser(User $user, int $tenantId): User
    {
        $belongsToTenant = $user->tenants()->where('tenants.id', $tenantId)->exists();

        abort_unless($belongsToTenant, 404);

        return $user;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $tenantId = $this->tenantIdForRequest($request);

        $query = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->with(['roles:id,name,display_name', 'branch:id,name']);

        if ($search = $request->get('search')) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($roleFilter = $request->get('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $roleFilter));
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        $users = $query->orderBy('name')
            ->paginate($perPage);

        return ApiResponse::paginated($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);
        $tenantId = $this->tenantIdForRequest($request);
        $validated = $request->validated();

        try {
            $user = DB::transaction(function () use ($validated, $tenantId) {
                // SEC-08: `is_active` e `current_tenant_id` saíram de $fillable.
                // O constructor mass-assignable preenche apenas os fillable;
                // os campos sensíveis são atribuídos via forceFill() a partir
                // de valores derivados do contexto (tenantId do user logado),
                // não do body do request.
                $user = new User([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => $validated['password'],
                    'branch_id' => $validated['branch_id'] ?? null,
                ]);
                $user->forceFill([
                    'is_active' => $validated['is_active'] ?? true,
                    'current_tenant_id' => $tenantId,
                    'tenant_id' => max(0, $tenantId),
                ]);
                $user->save();

                $user->tenants()->attach($tenantId, ['is_default' => true]);

                if (! empty($validated['roles'])) {
                    $user->syncRoles($validated['roles']);
                }

                return $user;
            });

            $user->load('roles:id,name,display_name');

            AuditLog::log('created', "Usuário {$user->name} criado", $user);

            return ApiResponse::data($user, 201);
        } catch (\Exception $e) {
            Log::error('User store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar usuário.', 500);
        }
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        $user->load(['roles:id,name,display_name', 'roles.permissions:id,name']);

        return ApiResponse::data($user);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $tenantId = $this->tenantIdForRequest($request);
        $this->resolveTenantUser($user, $tenantId);
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($user, $validated) {
                // SEC-08: `is_active` saiu de $fillable. Extraímos separadamente
                // e aplicamos via forceFill() — o FormRequest autoriza
                // (Policy update), então mudança administrativa continua válida.
                $data = collect($validated)->except(['roles', 'password', 'is_active'])->toArray();

                if (! empty($validated['password']) && trim($validated['password']) !== '') {
                    $data['password'] = $validated['password'];
                }

                $user->update($data);

                if (array_key_exists('is_active', $validated)) {
                    $user->forceFill(['is_active' => (bool) $validated['is_active']])->save();
                }

                if (isset($validated['roles'])) {
                    $user->syncRoles($validated['roles']);
                }
            });

            $user->load('roles:id,name,display_name');

            AuditLog::log('updated', "Usuário {$user->name} atualizado", $user);

            return ApiResponse::data($user);
        } catch (\Exception $e) {
            Log::error('User update failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar usuário.', 500);
        }
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        $tenantId = $this->tenantIdForRequest($request);
        $this->resolveTenantUser($user, $tenantId);

        if ($user->id === $request->user()->id) {
            return ApiResponse::message('Você não pode excluir sua própria conta.', 422);
        }

        // Verificar dependências em múltiplas tabelas antes de excluir (com escopo de tenant)
        // Order matters: check specific tables before generic ones (crm_deals before central_items)
        $dependencyTables = [
            'work_orders' => ['assigned_to', 'created_by'],
            'quotes' => ['created_by'],
            'service_calls' => ['assigned_to', 'created_by'],
            'schedules' => ['technician_id'],
            'expenses' => ['created_by'],
            'commission_events' => ['user_id'],
            'crm_deals' => ['assigned_to'],
            'central_items' => ['assignee_user_id', 'created_by_user_id', 'closed_by'],
            'technician_cash_transactions' => ['created_by'],
        ];

        foreach ($dependencyTables as $table => $columns) {
            $query = DB::table($table)->where('tenant_id', $tenantId);
            if (count($columns) === 1) {
                $query->where($columns[0], $user->id);
            } else {
                $query->where(function ($q) use ($user, $columns) {
                    foreach ($columns as $col) {
                        $q->orWhere($col, $user->id);
                    }
                });
            }
            if ($query->exists()) {
                $prettyName = str_replace('_', ' ', $table);

                return ApiResponse::message(
                    "Este usuário possui registros vinculados em '{$prettyName}'. Desative-o ao invés de excluir.",
                    422
                );
            }
        }

        try {
            DB::transaction(function () use ($user) {
                $user->tokens()->delete();
                $user->tenants()->detach();
                $user->forceDelete();
            });

            AuditLog::log('deleted', "Usuário {$user->name} excluído", $user);

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('User delete failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir usuário.', 500);
        }
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        if ($user->id === $request->user()->id) {
            return ApiResponse::message('Você não pode desativar sua própria conta.', 422);
        }

        try {
            DB::transaction(function () use ($user) {
                // SEC-08: `is_active` saiu de $fillable — forceFill em path legítimo.
                $user->forceFill(['is_active' => ! $user->is_active])->save();

                if (! $user->is_active) {
                    $user->tokens()->delete();
                }
            });

            AuditLog::log('status_changed', "Usuário {$user->name} ".($user->is_active ? 'ativado' : 'desativado'), $user);

            return ApiResponse::data(['is_active' => $user->is_active]);
        } catch (\Exception $e) {
            Log::error('User toggleActive failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao alterar status do usuário.', 500);
        }
    }

    /**
     * Reset de senha por admin (IAM).
     */
    public function resetPassword(ResetPasswordRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $tenantId = $this->tenantIdForRequest($request);
        $this->resolveTenantUser($user, $tenantId);

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($user, $validated) {
                $user->update(['password' => $validated['password']]);
                $user->tokens()->delete();
            });

            AuditLog::log('updated', "Senha do usuário {$user->name} resetada", $user);

            return ApiResponse::message('Senha atualizada.');
        } catch (\Exception $e) {
            Log::error('User resetPassword failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao redefinir senha.', 500);
        }
    }

    /**
     * POST /users/{user}/roles — sincroniza roles de um usuário.
     */
    public function assignRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $tenantId = $this->tenantIdForRequest($request);
        $this->resolveTenantUser($user, $tenantId);

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($user, $validated) {
                $user->syncRoles($validated['roles']);
            });

            $user->load('roles:id,name,display_name');

            AuditLog::log('updated', "Roles do usuário {$user->name} atualizadas", $user);

            return ApiResponse::data($user);
        } catch (\Exception $e) {
            Log::error('Assign roles failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atribuir roles.', 500);
        }
    }

    /**
     * Dropdown: lista users ativos por role(s).
     * GET /users/by-role/tecnico ou /users/by-role/tecnico,vendedor
     */
    public function byRole(Request $request, string $role): JsonResponse
    {
        $roles = explode(',', $role);
        $tenantId = $this->tenantIdForRequest($request);

        $users = $this->tenantUsersByRoles($tenantId, $roles);

        return ApiResponse::data($users);
    }

    /**
     * Lista simplificada de tecnicos ativos para modulos operacionais.
     * GET /technicians/options
     */
    public function techniciansOptions(Request $request): JsonResponse
    {
        $tenantId = $this->tenantIdForRequest($request);
        $users = $this->tenantUsersByRoles($tenantId, [Role::TECNICO]);

        return ApiResponse::data($users);
    }

    private function tenantUsersByRoles(int $tenantId, array $roles)
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($tenantId) {
                $query
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($tenantQuery) => $tenantQuery->where('tenants.id', $tenantId));
            })
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->with('roles:id,name,display_name')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * GET /users/{user}/sessions — lista tokens/sessões ativas de um usuário.
     */
    public function sessions(Request $request, User $user): JsonResponse
    {
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        $viewingOwnSessions = $request->user()->id === $user->id;
        $currentTokenId = $viewingOwnSessions
            ? $request->user()->currentAccessToken()?->id
            : null;

        $tokens = $user->tokens()
            ->select('id', 'name', 'last_used_at', 'created_at', 'expires_at')
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'is_current' => $currentTokenId !== null && $currentTokenId === $token->id,
            ]);

        return ApiResponse::data($tokens);
    }

    /**
     * DELETE /users/{user}/sessions/{tokenId} — revoga uma sessão específica.
     */
    public function revokeSession(Request $request, User $user, int $tokenId): JsonResponse
    {
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        $deleted = $user->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            return ApiResponse::message('Sessão não encontrada.', 404);
        }

        return ApiResponse::message('Sessão revogada com sucesso.');
    }

    /**
     * POST /users/bulk-toggle-active — ativa/desativa múltiplos usuários.
     */
    public function bulkToggleActive(BulkToggleActiveRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tenantId = $this->tenantIdForRequest($request);
        $currentUserId = $request->user()->id;

        // Filter out the current user and only target tenant users
        $userIds = collect($validated['user_ids'])
            ->reject(fn ($id) => (int) $id === $currentUserId)
            ->values()
            ->toArray();

        if (empty($userIds)) {
            return ApiResponse::message('Nenhum usuário válido para alterar.', 422);
        }

        try {
            DB::beginTransaction();

            $affectedUsers = User::whereIn('id', $userIds)
                ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
                ->get();

            $affected = $affectedUsers->count();
            User::whereIn('id', $affectedUsers->pluck('id')->toArray())
                ->update(['is_active' => $validated['is_active']]);

            // Revoke tokens for deactivated users
            if (! $validated['is_active']) {
                DB::table('personal_access_tokens')
                    ->whereIn('tokenable_id', $affectedUsers->pluck('id')->toArray())
                    ->where('tokenable_type', User::class)
                    ->delete();
            }

            DB::commit();

            $action = $validated['is_active'] ? 'ativados' : 'desativados';
            $names = $affectedUsers->pluck('name')->implode(', ');
            AuditLog::log('status_changed', "{$affected} usuário(s) {$action} em lote: {$names}");

            return ApiResponse::data(
                ['affected' => $affected],
                200,
                ['message' => "{$affected} usuário(s) ".($validated['is_active'] ? 'ativado(s)' : 'desativado(s)').'.', 'affected' => $affected]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk toggle active failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao alterar status dos usuários.', 500);
        }
    }

    /**
     * POST /users/{user}/force-logout — revoga TODAS as sessões de um usuário.
     */
    public function forceLogout(Request $request, User $user): JsonResponse
    {
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        if ($user->id === $request->user()->id) {
            return ApiResponse::message('Use o logout normal para encerrar sua própria sessão.', 422);
        }

        try {
            $count = $user->tokens()->count();
            $user->tokens()->delete();

            AuditLog::log('logout', "Forçado logout do usuário {$user->name} ({$count} sessões)", $user);

            return response()->json([
                'data' => ['revoked' => $count],
                'revoked' => $count,
                'message' => "{$count} sessão(ões) revogada(s) com sucesso.",
            ]);
        } catch (\Exception $e) {
            Log::error('Force logout failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao revogar sessões.', 500);
        }
    }

    /**
     * GET /users/export — exporta lista de usuários como CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $tenantId = $this->tenantIdForRequest($request);

        $query = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->with('roles:id,name,display_name');

        if ($search = $request->get('search')) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($roleFilter = $request->get('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.name', $roleFilter));
        }

        $users = $query->orderBy('name')->get();

        $filename = 'usuarios_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($users) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($handle, ['Nome', 'E-mail', 'Telefone', 'Roles', 'Status', 'Último Login', 'Criado em'], ';');

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->name,
                    $user->email,
                    $user->phone ?? '-',
                    $user->roles->map(fn ($r) => $r->display_name ?: $r->name)->implode(', '),
                    $user->is_active ? 'Ativo' : 'Inativo',
                    $user->last_login_at?->format('d/m/Y H:i') ?? 'Nunca',
                    $user->created_at?->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * GET /users/stats — métricas do IAM dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $this->tenantIdForRequest($request);

        $base = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId));

        $total = (clone $base)->count();
        $active = (clone $base)->where('is_active', true)->count();
        $inactive = $total - $active;
        $neverLogged = (clone $base)->whereNull('last_login_at')->count();

        $byRole = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('user_tenants', function ($join) use ($tenantId) {
                $join->on('user_tenants.user_id', '=', 'model_has_roles.model_id')
                    ->where('user_tenants.tenant_id', $tenantId);
            })
            ->where('model_has_roles.model_type', User::class)
            ->selectRaw('roles.name, COUNT(*) as count')
            ->groupBy('roles.name')
            ->pluck('count', 'name');

        $recentUsers = (clone $base)
            ->select('id', 'name', 'email', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return ApiResponse::data([
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'never_logged' => $neverLogged,
            'by_role' => $byRole,
            'recent_users' => $recentUsers,
        ]);
    }

    /**
     * GET /users/{user}/permissions — lista permissões diretas do usuário.
     */
    public function directPermissions(Request $request, User $user): JsonResponse
    {
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        return ApiResponse::data([
            'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name'),
            'all_permissions' => $user->getAllPermissions()->pluck('name'),
            'denied_permissions' => $user->getDeniedPermissionsList(),
            'effective_permissions' => $user->getEffectivePermissions()->pluck('name')->values(),
        ]);
    }

    /**
     * POST /users/{user}/permissions — atribui permissões diretas ao usuário.
     */
    public function grantPermissions(GrantPermissionsRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        $validated = $request->validated();

        try {
            $user->givePermissionTo($validated['permissions']);

            AuditLog::log('updated', "Permissões diretas concedidas ao usuário {$user->name}: ".implode(', ', $validated['permissions']), $user);

            return ApiResponse::data(
                ['direct_permissions' => $user->getDirectPermissions()->pluck('name')],
                200,
                ['message' => 'Permissões concedidas.']
            );
        } catch (\Exception $e) {
            Log::error('Grant permissions failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao conceder permissões.', 500);
        }
    }

    /**
     * DELETE /users/{user}/permissions — revoga permissões diretas do usuário.
     */
    public function revokePermissions(RevokePermissionsRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        $validated = $request->validated();

        try {
            foreach ($validated['permissions'] as $perm) {
                $user->revokePermissionTo($perm);
            }

            AuditLog::log('updated', "Permissões diretas revogadas do usuário {$user->name}: ".implode(', ', $validated['permissions']), $user);

            return ApiResponse::data(
                ['direct_permissions' => $user->getDirectPermissions()->pluck('name')],
                200,
                ['message' => 'Permissões revogadas.']
            );
        } catch (\Exception $e) {
            Log::error('Revoke permissions failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao revogar permissões.', 500);
        }
    }

    /**
     * PUT /users/{user}/permissions — sincroniza permissões diretas (substitui todas).
     */
    public function syncDirectPermissions(SyncDirectPermissionsRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        $validated = $request->validated();

        try {
            $user->syncPermissions($validated['permissions']);

            AuditLog::log('updated', "Permissões diretas do usuário {$user->name} sincronizadas", $user);

            return ApiResponse::data(
                ['direct_permissions' => $user->getDirectPermissions()->pluck('name')],
                200,
                ['message' => 'Permissões sincronizadas.']
            );
        } catch (\Exception $e) {
            Log::error('Sync permissions failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao sincronizar permissões.', 500);
        }
    }

    /**
     * GET /users/{user}/denied-permissions — lista permissões negadas do usuário.
     */
    public function deniedPermissions(Request $request, User $user): JsonResponse
    {
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        return ApiResponse::data([
            'denied_permissions' => $user->getDeniedPermissionsList(),
        ]);
    }

    /**
     * PUT /users/{user}/denied-permissions — sincroniza permissões negadas.
     */
    public function syncDeniedPermissions(SyncDeniedPermissionsRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $this->resolveTenantUser($user, $this->tenantIdForRequest($request));

        $validated = $request->validated();

        try {
            // SEC-08: `denied_permissions` saiu de $fillable — forceFill em path legítimo.
            $user->forceFill(['denied_permissions' => $validated['denied_permissions']])->save();

            AuditLog::log(
                'updated',
                "Permissões negadas do usuário {$user->name} atualizadas: ".(empty($validated['denied_permissions']) ? 'nenhuma' : implode(', ', $validated['denied_permissions'])),
                $user
            );

            return ApiResponse::data(
                ['denied_permissions' => $user->getDeniedPermissionsList()],
                200,
                ['message' => 'Permissões negadas atualizadas.']
            );
        } catch (\Exception $e) {
            Log::error('Sync denied permissions failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar permissões negadas.', 500);
        }
    }

    /**
     * GET /users/{user}/audit-trail — histórico de ações de um usuário.
     */
    public function auditTrail(Request $request, User $user): JsonResponse
    {
        $tenantId = $this->tenantIdForRequest($request);
        $this->resolveTenantUser($user, $tenantId);

        $logs = AuditLog::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return ApiResponse::paginated($logs);
    }
}
