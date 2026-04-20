<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendPasswordResetLinkRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;

class PasswordResetController extends Controller
{
    /**
     * Envia link de redefinição de senha por email.
     */
    public function sendResetLink(SendPasswordResetLinkRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return ApiResponse::message(
            'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.'
        );
    }

    /**
     * Redefine a senha usando o token.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // AUTH-002: Confiar no cast 'hashed' do Model — sem Hash::make() manual.
                // sec-07 (Re-auditoria Camada 1 r3): persistir password_changed_at
                // para suportar politicas de rotacao (OWASP ASVS V2.1.10). Coluna
                // fora de $fillable (SEC-08) — atribuida via forceFill.
                $fill = ['password' => $password];
                if (Schema::hasColumn('users', 'password_changed_at')) {
                    $fill['password_changed_at'] = now();
                }
                $user->forceFill($fill)->save();

                // Revoga todos os tokens Sanctum existentes (API / mobile / portal).
                $user->tokens()->delete();

                // sec-07: invalida QUALQUER sessao web stateful do usuario em
                // outros devices, rotacionando o remember_token. Usa a senha
                // ja setada (hashed pelo cast) — Auth::logoutOtherDevices valida
                // com Hash::check antes de regenerar, entao passamos a senha
                // em texto claro recebida no request.
                try {
                    $guard = Auth::guard('web');
                    if ($guard instanceof SessionGuard) {
                        $guard->setUser($user);
                        $guard->logoutOtherDevices($password);
                    }
                } catch (\Throwable $e) {
                    // Falha de guard web (ex: ambiente sem sessao) nao deve
                    // bloquear reset — mas e registrada para observabilidade.
                    Log::warning(
                        'PasswordReset: logoutOtherDevices falhou',
                        ['user_id' => $user->id, 'message' => $e->getMessage()]
                    );
                }

                // Registra no audit log
                $tenantId = $user->current_tenant_id ?? $user->tenant_id;
                if ($tenantId) {
                    app()->instance('current_tenant_id', $tenantId);
                }
                // sec-10 (LGPD Art. 46): description sem PII; ator em user_id (FK).
                AuditLog::log('password_reset', 'Senha redefinida via fluxo de recuperação', $user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ApiResponse::message('Senha redefinida com sucesso. Faça login com a nova senha.');
        }

        $messages = [
            Password::INVALID_TOKEN => 'Token de redefinição inválido ou expirado.',
            Password::INVALID_USER => 'Usuário não encontrado.',
            Password::RESET_THROTTLED => 'Aguarde antes de solicitar outra redefinição.',
        ];

        return ApiResponse::message($messages[$status] ?? 'Erro ao redefinir senha.', 422);
    }
}
