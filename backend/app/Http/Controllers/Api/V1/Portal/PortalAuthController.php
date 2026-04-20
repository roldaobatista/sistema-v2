<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalLoginRequest;
use App\Http\Resources\ClientPortalUserResource;
use App\Models\ClientPortalUser;
use App\Models\Contract;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PortalAuthController extends Controller
{
    /**
     * Número de falhas de senha consecutivas antes de travar a conta.
     * Alinhado com `AuthController` do painel interno.
     */
    private const LOCKOUT_THRESHOLD = 5;

    /**
     * Duração do lockout persistente (em minutos) após atingir o threshold.
     */
    private const LOCKOUT_DURATION_MINUTES = 30;

    private function portalUser(Request $request): ClientPortalUser
    {
        $user = $request->user();

        if (! $user instanceof ClientPortalUser || ! $user->tokenCan('portal:access')) {
            abort(403, 'Acesso restrito ao portal do cliente.');
        }

        return $user;
    }

    public function login(PortalLoginRequest $request): JsonResponse
    {
        try {
            $email = strtolower((string) $request->input('email'));
            $throttleKey = sprintf(
                'portal_login_attempts:%s:%s',
                $request->ip(),
                $email
            );

            $attempts = (int) Cache::get($throttleKey, 0);
            if ($attempts >= self::LOCKOUT_THRESHOLD) {
                $ttl = Cache::get($throttleKey.':ttl', 0);
                $remainingMinutes = ($ttl > 0 && $ttl > now()->timestamp)
                    ? (int) ceil(($ttl - now()->timestamp) / 60)
                    : 15;

                return ApiResponse::message(
                    "Muitas tentativas de login. Tente novamente em {$remainingMinutes} minutos.",
                    429
                );
            }

            $tenantId = app()->bound('current_tenant_id') ? (int) app('current_tenant_id') : null;
            $users = ClientPortalUser::query()
                ->with('customer')
                ->where('email', $email)
                ->when($tenantId && $tenantId > 0, fn ($query) => $query->where('tenant_id', $tenantId))
                ->limit(2)
                ->get();

            $user = $users->count() === 1 ? $users->first() : null;

            // sec-portal-lockout-not-enforced-on-login (Camada 1 r4 Batch B):
            // Enforçar lockout PERSISTENTE em banco antes de validar senha.
            // Bypass via rotação de IP era possível porque só havia throttle
            // de cache por IP+email. `locked_until`/`failed_login_attempts`
            // já existiam no schema mas eram inertes.
            if ($user && $user->locked_until && $user->locked_until->isFuture()) {
                return ApiResponse::message(
                    'Conta temporariamente bloqueada por excesso de tentativas. Tente novamente mais tarde.',
                    423
                );
            }

            if (! $user || ! Hash::check($request->password, $user->password)) {
                Cache::put($throttleKey, $attempts + 1, now()->addMinutes(15));
                Cache::put($throttleKey.':ttl', now()->addMinutes(15)->timestamp, now()->addMinutes(15));

                // Incrementa contador persistente e trava conta se ultrapassar threshold.
                if ($user) {
                    $persistedAttempts = ((int) $user->failed_login_attempts) + 1;
                    $update = ['failed_login_attempts' => $persistedAttempts];

                    if ($persistedAttempts >= self::LOCKOUT_THRESHOLD) {
                        $update['locked_until'] = now()->addMinutes(self::LOCKOUT_DURATION_MINUTES);
                    }

                    // forceFill: hardening fields não estão em $fillable por design.
                    $user->forceFill($update)->save();
                }

                throw ValidationException::withMessages([
                    'email' => ['As credenciais fornecidas estao incorretas.'],
                ]);
            }

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'email' => ['Sua conta esta inativa.'],
                ]);
            }

            $hasActiveContract = Contract::where('tenant_id', $user->tenant_id)
                ->where('customer_id', $user->customer_id)
                ->where('status', 'active')
                ->exists();

            if (! $hasActiveContract) {
                throw ValidationException::withMessages([
                    'email' => ['Acesso bloqueado: Nenhum contrato ativo de prestação de serviço foi encontrado para esta conta.'],
                ]);
            }

            Cache::forget($throttleKey);
            Cache::forget($throttleKey.':ttl');

            // Reset dos contadores persistentes em login bem-sucedido.
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'last_login_at' => now(),
            ])->save();

            $token = $user->createToken('portal-token', ['portal:access'])->plainTextToken;

            return ApiResponse::data([
                'token' => $token,
                'user' => new ClientPortalUserResource($user),
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::message('Dados invalidos.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('PortalAuth login failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao realizar login', 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::data(new ClientPortalUserResource($this->portalUser($request)->load('customer')));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->portalUser($request)->currentAccessToken()?->delete();

        return ApiResponse::noContent();
    }
}
