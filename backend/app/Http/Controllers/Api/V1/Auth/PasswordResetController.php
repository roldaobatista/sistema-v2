<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendPasswordResetLinkRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

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
                // AUTH-002: Confiar no cast 'hashed' do Model — sem Hash::make() manual
                $user->forceFill([
                    'password' => $password,
                ])->save();

                // Revoga todos os tokens existentes por segurança
                $user->tokens()->delete();

                // Registra no audit log
                $tenantId = $user->current_tenant_id ?? $user->tenant_id;
                if ($tenantId) {
                    app()->instance('current_tenant_id', $tenantId);
                }
                AuditLog::log('password_reset', "Senha redefinida via 'Esqueci minha senha' para {$user->email}", $user);
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
