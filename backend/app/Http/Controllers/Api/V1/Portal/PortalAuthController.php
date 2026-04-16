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
            if ($attempts >= 5) {
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

            if (! $user || ! Hash::check($request->password, $user->password)) {
                Cache::put($throttleKey, $attempts + 1, now()->addMinutes(15));
                Cache::put($throttleKey.':ttl', now()->addMinutes(15)->timestamp, now()->addMinutes(15));

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

            $user->update(['last_login_at' => now()]);

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
