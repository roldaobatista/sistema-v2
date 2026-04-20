<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('currentTenant');

        return ApiResponse::data([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'tenant' => $user->currentTenant,
                'tenant_id' => $user->current_tenant_id,
                'permissions' => $user->getEffectivePermissions()->pluck('name')->values(),
                'roles' => $user->getRoleNames(),
                'role_details' => $user->roles->map(fn ($role) => [
                    'name' => $role->name,
                    'display_name' => $role->display_name ?: $role->name,
                ])->values(),
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (isset($validated['password']) && ! Hash::check($validated['current_password'], $user->password)) {
            return ApiResponse::message('Senha atual incorreta.', 422);
        }

        try {
            unset($validated['current_password']);

            DB::transaction(function () use ($user, $validated) {
                $user->update($validated);
            });

            AuditLog::log('updated', 'Perfil do usuário atualizado', $user);

            return ApiResponse::message('Perfil atualizado.', 200, [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Profile update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao atualizar perfil.', 500);
        }
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return ApiResponse::message('Senha atual incorreta.', 422);
        }

        try {
            DB::transaction(function () use ($user, $validated) {
                $user->update(['password' => $validated['new_password']]);

                $currentTokenId = $user->currentAccessToken()?->id;
                $user->tokens()->where('id', '!=', $currentTokenId)->delete();
            });

            return ApiResponse::message('Senha alterada com sucesso.');
        } catch (\Exception $e) {
            Log::error('Password change failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao alterar senha.', 500);
        }
    }
}
