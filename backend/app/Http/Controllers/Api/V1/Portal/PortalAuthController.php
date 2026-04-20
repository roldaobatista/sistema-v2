<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalLoginRequest;
use App\Http\Resources\ClientPortalUserResource;
use App\Models\AuditLog;
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

    /**
     * Mensagem genérica para qualquer falha de autenticação.
     * sec-portal-tenant-enumeration-bypass (§14.30): respostas uniformes
     * evitam que atacante distinga tenant inválido, email inexistente,
     * senha errada, conta inativa ou sem contrato ativo via response body.
     */
    private const GENERIC_AUTH_FAILURE = 'Credenciais invalidas.';

    /**
     * Hash bcrypt válido pré-computado de string aleatória.
     * Usado para executar Hash::check em tempo constante quando o usuário não
     * existe — mitiga timing attack que distinguiria "email inexistente" de
     * "senha errada" pela diferença de tempo entre com/sem Hash::check real.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$10$1umBmv95QbUpHlWyA3/EBeNDpyBoyDjD9SHsOOIW/icFav2uqW6Pu';

    /**
     * Registra audit trail do portal (sec-portal-audit-missing — §14.31).
     * Falha de AuditLog não bloqueia o fluxo de login/logout — apenas loga.
     */
    private function audit(string $action, string $description, ?ClientPortalUser $user = null): void
    {
        try {
            if ($user && $user->tenant_id) {
                app()->instance('current_tenant_id', (int) $user->tenant_id);
            }
            AuditLog::log($action, $description, $user);
        } catch (\Throwable $e) {
            Log::warning('PortalAuth audit log failed', [
                'action' => $action,
                'message' => $e->getMessage(),
            ]);
        }
    }

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

            // sec-portal-throttle-toctou (§Batch C): incremento atômico.
            // Cache::add cria a chave em 0 apenas se não existir; Cache::increment
            // é atômico no driver Redis/Memcached/Array. Evita race condition
            // em que duas requests paralelas leem o mesmo $attempts e gravam o
            // mesmo valor final, permitindo ultrapassar o threshold.
            Cache::add($throttleKey, 0, now()->addMinutes(15));
            Cache::add($throttleKey.':ttl', now()->addMinutes(15)->timestamp, now()->addMinutes(15));

            $attempts = (int) Cache::get($throttleKey, 0);
            if ($attempts >= self::LOCKOUT_THRESHOLD) {
                $ttl = Cache::get($throttleKey.':ttl', 0);
                $remainingMinutes = ($ttl > 0 && $ttl > now()->timestamp)
                    ? (int) ceil(($ttl - now()->timestamp) / 60)
                    : 15;

                $this->audit('portal_login_locked', 'Bloqueado por excesso de tentativas (cache)');

                return ApiResponse::message(
                    "Muitas tentativas de login. Tente novamente em {$remainingMinutes} minutos.",
                    429
                );
            }

            // sec-portal-tenant-enumeration-bypass (§14.30): mesmo quando
            // `current_tenant_id` não está bindado, jamais retornar usuário
            // se houver colisão entre tenants para o mesmo email (limit 2 +
            // exigir count=1). Sem binding e 2 matches, trata como "não
            // encontrado" (resposta genérica).
            $tenantId = app()->bound('current_tenant_id') ? (int) app('current_tenant_id') : null;
            $users = ClientPortalUser::query()
                ->with('customer')
                ->where('email', $email)
                ->when($tenantId && $tenantId > 0, fn ($query) => $query->where('tenant_id', $tenantId))
                ->limit(2)
                ->get();

            $user = $users->count() === 1 ? $users->first() : null;

            // sec-portal-lockout-not-enforced-on-login (Batch B):
            // Lockout persistente em banco. Mantém 423 específico — é um
            // estado do usuário, não distinção de credencial, e o cliente
            // legítimo precisa saber que deve esperar.
            if ($user && $user->locked_until && $user->locked_until->isFuture()) {
                $this->audit(
                    'portal_login_locked',
                    'Tentativa de login em conta bloqueada (locked_until futuro)',
                    $user
                );

                return ApiResponse::message(
                    'Conta temporariamente bloqueada por excesso de tentativas. Tente novamente mais tarde.',
                    423
                );
            }

            // Verificação de senha em tempo constante: se usuário não encontrado,
            // executa Hash::check contra hash dummy para evitar timing attack.
            $passwordOk = $user
                ? Hash::check($request->password, $user->password)
                : Hash::check($request->password, self::DUMMY_PASSWORD_HASH);

            if (! $user || ! $passwordOk) {
                // Atomic increment — TOCTOU fix.
                Cache::increment($throttleKey);

                if ($user) {
                    // Incrementa contador persistente e trava conta se ultrapassar threshold.
                    $persistedAttempts = ((int) $user->failed_login_attempts) + 1;
                    $update = ['failed_login_attempts' => $persistedAttempts];

                    if ($persistedAttempts >= self::LOCKOUT_THRESHOLD) {
                        $update['locked_until'] = now()->addMinutes(self::LOCKOUT_DURATION_MINUTES);
                    }

                    // forceFill: hardening fields não estão em $fillable por design.
                    $user->forceFill($update)->save();
                }

                // sec-portal-audit-missing (§14.31): registrar falha.
                $this->audit('portal_login_failed', 'Falha de autenticação no portal', $user);

                return ApiResponse::message(self::GENERIC_AUTH_FAILURE, 422);
            }

            // sec-portal-login-no-email-verification (§14.32 ref):
            // Espelha AuthController web — bloqueia login se email não verificado.
            if (
                config('portal.require_email_verified', true)
                && is_null($user->email_verified_at)
            ) {
                $this->audit(
                    'portal_login_failed',
                    'Login negado: email não verificado',
                    $user
                );

                return ApiResponse::message(
                    'E-mail não verificado. Verifique sua caixa de entrada antes de fazer login.',
                    403
                );
            }

            // sec-portal-tenant-enumeration-bypass (§14.30): inativo e sem
            // contrato retornam o MESMO status/mensagem que senha errada —
            // não vazar estado interno via distinção de response body.
            if (! $user->is_active) {
                Cache::increment($throttleKey);
                $this->audit('portal_login_failed', 'Login negado: conta inativa', $user);

                return ApiResponse::message(self::GENERIC_AUTH_FAILURE, 422);
            }

            $hasActiveContract = Contract::where('tenant_id', $user->tenant_id)
                ->where('customer_id', $user->customer_id)
                ->where('status', 'active')
                ->exists();

            if (! $hasActiveContract) {
                Cache::increment($throttleKey);
                $this->audit(
                    'portal_login_failed',
                    'Login negado: nenhum contrato ativo',
                    $user
                );

                return ApiResponse::message(self::GENERIC_AUTH_FAILURE, 422);
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

            // sec-portal-audit-missing (§14.31): audit trail em login OK.
            $this->audit('portal_login_success', 'Login bem-sucedido no portal', $user);

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
        $user = $this->portalUser($request);
        $user->currentAccessToken()?->delete();

        // sec-portal-audit-missing (§14.31): audit trail em logout.
        $this->audit('portal_logout', 'Logout do portal', $user);

        return ApiResponse::noContent();
    }
}
