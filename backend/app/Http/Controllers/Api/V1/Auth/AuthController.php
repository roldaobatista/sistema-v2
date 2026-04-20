<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SwitchTenantRequest;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $normalizedEmail = $validated['email'];

            // SEC-04: contador de tentativas ATÔMICO — evita TOCTOU em
            // cenários de credential stuffing concorrente (OWASP ASVS
            // V2.2.1). Cache::add seta o valor 0 + TTL idempotentemente
            // apenas na 1ª tentativa da janela; Cache::increment é
            // atômico no driver (Redis/Memcached/Array).
            $throttleKey = 'login_attempts:'.$request->ip().':'.$normalizedEmail;
            $windowExpiration = now()->addMinutes(15);

            $attempts = (int) Cache::get($throttleKey, 0);

            if ($attempts >= 5) {
                $ttl = (int) Cache::get($throttleKey.':ttl', 0);
                $remainingMinutes = ($ttl > 0 && $ttl > now()->timestamp)
                    ? (int) ceil(($ttl - now()->timestamp) / 60)
                    : 15;

                return ApiResponse::message(
                    'Conta bloqueada. Muitas tentativas de login. Tente novamente em '.max(1, $remainingMinutes).' minutos.',
                    429
                );
            }

            $user = User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            if (! $user || ! Hash::check($validated['password'], $user->password)) {
                // Seeds idempotentes: só gravam se ainda não existe no
                // cache. TTL da janela é fixado na primeira falha e
                // preservado em incrementos subsequentes.
                Cache::add($throttleKey, 0, $windowExpiration);
                Cache::add($throttleKey.':ttl', $windowExpiration->timestamp, $windowExpiration);
                // Incremento atômico — duas requisições concorrentes
                // nunca produzem o mesmo valor final.
                Cache::increment($throttleKey);

                throw ValidationException::withMessages([
                    'email' => ['Credenciais inválidas.'],
                ]);
            }

            if (! $user->is_active) {
                return ApiResponse::message('Conta desativada.', 403);
            }

            Cache::forget($throttleKey);
            Cache::forget($throttleKey.':ttl');

            $user->tokens()
                ->where('name', 'api')
                ->delete();

            [$resolvedTenantId, $finalTenant] = $this->resolveActiveTenantForLogin($user);

            $abilities = $resolvedTenantId ? ["tenant:{$resolvedTenantId}"] : ['*'];
            $token = $user->createToken('api', $abilities)->plainTextToken;

            $user->forceFill([
                'last_login_at' => now(),
                'current_tenant_id' => $resolvedTenantId,
            ])->save();

            if ($resolvedTenantId) {
                app()->instance('current_tenant_id', $resolvedTenantId);
                setPermissionsTeamId($resolvedTenantId);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            } else {
                app()->instance('current_tenant_id', 0);
                setPermissionsTeamId(0);
            }

            // Sanctum não popula auth() na request de login (só emite token).
            // Setamos o usuário aqui para que AuditLog::log registre user_id correto.
            Auth::setUser($user);

            try {
                // sec-10 (LGPD Art. 46): description sem PII. Ator fica em user_id (FK).
                AuditLog::log('login', 'Login realizado', $user);
            } catch (\Throwable $e) {
                Log::warning('AuditLog::log login failed', ['message' => $e->getMessage()]);
            }

            $permissions = [];
            $roles = [];
            $roleDetails = [];

            try {
                $permissions = $user->getEffectivePermissions()->pluck('name')->values()->all();
                $roles = $user->getRoleNames()->all();
                $roleDetails = $user->roles->map(fn ($role) => [
                    'name' => $role->name,
                    'display_name' => ($role->display_name ?? null) ?: $role->name,
                ])->values()->all();
            } catch (\Throwable $e) {
                Log::warning('Login: permissions/roles load failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);
            }

            $responseData = [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $resolvedTenantId,
                    'tenant' => $finalTenant,
                    'permissions' => $permissions,
                    'roles' => $roles,
                    'role_details' => $roleDetails,
                ],
            ];

            $response = response()->json(array_merge(
                $responseData,
                ['data' => $responseData]
            ));

            if (config('sanctum.use_token_cookie', false)) {
                $cookieName = config('sanctum.cookie_name', 'auth_token');
                $minutes = (int) config('sanctum.expiration', 10080);
                $response->cookie($cookieName, $token, $minutes, '/', null, $request->secure(), true, false, 'lax');
            }

            return $response;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Login Error', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::message('Erro interno ao realizar login.', 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $tenantId = app()->bound('current_tenant_id') ? (int) app('current_tenant_id') : (int) ($user->current_tenant_id ?? 0);
            $tenant = ($tenantId > 0) ? Tenant::find($tenantId) : null;

            $permissions = [];
            $roles = [];
            $roleDetails = [];

            if ($tenantId > 0) {
                setPermissionsTeamId($tenantId);

                try {
                    $permissions = $user->getEffectivePermissions()->pluck('name')->values()->all();
                } catch (\Throwable $e) {
                    Log::warning('AuthController::me getEffectivePermissions failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);
                }

                try {
                    $roles = $user->getRoleNames()->all();
                    $roleDetails = $user->roles->map(fn ($role) => [
                        'name' => $role->name,
                        'display_name' => $role->display_name ?: $role->name,
                    ])->values()->all();
                } catch (\Throwable $e) {
                    Log::warning('AuthController::me roles failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);
                }
            }

            return ApiResponse::data([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'tenant_id' => $tenantId > 0 ? $tenantId : null,
                    'tenant' => $tenant,
                    'permissions' => $permissions,
                    'roles' => $roles,
                    'role_details' => $roleDetails,
                    'last_login_at' => $user->last_login_at,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('AuthController::me exception', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::message('Erro ao carregar dados do usuário.', 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->revokeRequestAccessToken($request);

        Auth::guard('web')->logout();

        $response = ApiResponse::message('Logout realizado.');
        if (config('sanctum.use_token_cookie', false)) {
            $response->withCookie(Cookie::forget(config('sanctum.cookie_name', 'auth_token')));
        }

        return $response;
    }

    public function myTenants(Request $request): JsonResponse
    {
        try {
            $tenants = $request->user()->tenants()->get(['tenants.id', 'tenants.name', 'tenants.document', 'tenants.status']);

            return ApiResponse::data($tenants);
        } catch (\Throwable $e) {
            Log::error('AuthController::myTenants exception', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::message('Erro ao listar empresas.', 500);
        }
    }

    public function switchTenant(SwitchTenantRequest $request): JsonResponse
    {
        $tenantId = (int) $request->validated()['tenant_id'];
        $user = $request->user();

        if (! $user->hasTenantAccess($tenantId)) {
            return ApiResponse::message('Acesso negado a esta empresa.', 403);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return ApiResponse::message('Empresa não encontrada.', 404);
        }

        if ($tenant->isInactive()) {
            return ApiResponse::message('Esta empresa está inativa. Contate o administrador.', 403);
        }

        $lockKey = 'switch_tenant:user_'.$user->id;
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return ApiResponse::message('Troca de empresa em andamento. Tente novamente em instantes.', 429);
        }

        try {
            $previousTenantId = $user->current_tenant_id;

            // SEC-08: `current_tenant_id` saiu de $fillable — usar forceFill().
            $user->forceFill(['current_tenant_id' => $tenantId])->save();

            // SEC-RA-13: revogar TODOS os tokens do usuário ao trocar de tenant.
            // Garante que nenhum token antigo (de outro tenant) permanece valido
            // em outros devices — alinhado com ability `tenant:X` do novo token.
            $user->tokens()->delete();

            $newToken = $user->createToken('api', ["tenant:{$tenant->id}"])->plainTextToken;

            app()->instance('current_tenant_id', $tenant->id);
            setPermissionsTeamId($tenant->id);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            // sec-10 (LGPD Art. 46): description sem PII; ator em user_id, alvo em auditable.
            AuditLog::log(
                'tenant_switch',
                "Tenant switch efetuado: #{$previousTenantId} → #{$tenant->id}",
                $tenant
            );

            $response = response()->json([
                'data' => ['tenant_id' => $tenantId, 'token' => $newToken],
                'tenant_id' => $tenantId,
                'token' => $newToken,
                'message' => 'Empresa alterada.',
            ]);
            if (config('sanctum.use_token_cookie', false)) {
                $minutes = (int) config('sanctum.expiration', 10080);
                $response->cookie(config('sanctum.cookie_name', 'auth_token'), $newToken, $minutes, '/', null, $request->secure(), true, false, 'lax');
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function resolveActiveTenantForLogin(User $user): array
    {
        $candidateIds = collect([
            (int) ($user->current_tenant_id ?? 0),
            (int) ($user->tenants()->wherePivot('is_default', true)->value('tenants.id') ?? 0),
            (int) ($user->tenant_id ?? 0),
            (int) ($user->tenants()->orderByDesc('user_tenants.is_default')->value('tenants.id') ?? 0),
        ])->filter(fn (int $id) => $id > 0)->unique()->values();

        foreach ($candidateIds as $candidateId) {
            $tenant = Tenant::find($candidateId);
            if (! $tenant || $tenant->isInactive()) {
                continue;
            }

            if ($user->hasTenantAccess((int) $tenant->id)) {
                return [(int) $tenant->id, $tenant];
            }
        }

        $fallbackTenant = $user->tenants()
            ->where('tenants.status', '!=', Tenant::STATUS_INACTIVE)
            ->orderByDesc('user_tenants.is_default')
            ->first();

        return [$fallbackTenant?->id, $fallbackTenant];
    }

    private function revokeRequestAccessToken(Request $request): void
    {
        /** @var mixed $token */
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();

            return;
        }

        $rawToken = $request->bearerToken();

        if (! $rawToken) {
            return;
        }

        PersonalAccessToken::findToken($rawToken)?->delete();
    }
}
