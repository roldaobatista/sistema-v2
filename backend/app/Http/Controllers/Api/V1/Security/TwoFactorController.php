<?php

namespace App\Http\Controllers\Api\V1\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\DisableTwoFactorRequest;
use App\Http\Requests\Security\EnableTwoFactorRequest;
use App\Http\Requests\Security\VerifyTwoFactorRequest;
use App\Notifications\TwoFactorVerificationCode;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    public function status(): JsonResponse
    {
        $user = auth()->user();
        $twoFa = $user->twoFactorAuth;

        return ApiResponse::data([
            'enabled' => (bool) $twoFa?->is_enabled,
            'method' => $twoFa?->method ?? null,
            'verified_at' => $twoFa?->verified_at,
        ]);
    }

    public function enable(EnableTwoFactorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::message('Senha incorreta', 403);
        }

        $secret = Str::random(32);

        $twoFactor = $user->twoFactorAuth()->firstOrNew(['user_id' => $user->id]);
        $twoFactor->fill([
            'method' => $validated['method'],
            'secret' => $secret,
            'is_enabled' => false,
        ]);
        $twoFactor->forceFill([
            'tenant_id' => $user->current_tenant_id ?? $user->tenant_id,
        ])->save();

        if ($validated['method'] === 'email') {
            $code = random_int(100000, 999999);
            cache()->put("2fa_verify_{$user->id}", $code, now()->addMinutes(10));
            $user->notify(new TwoFactorVerificationCode($code));
        }

        return ApiResponse::message('Código de verificação enviado', 200, [
            'method' => $validated['method'],
            'secret' => $validated['method'] === 'app' ? $secret : null,
        ]);
    }

    public function verify(VerifyTwoFactorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $twoFa = $user->twoFactorAuth;

        if (! $twoFa) {
            return ApiResponse::message('2FA não configurado', 422);
        }

        if ($twoFa->method === 'email') {
            $cachedCode = cache()->get("2fa_verify_{$user->id}");
            if ((string) $cachedCode !== $validated['code']) {
                return ApiResponse::message('Código inválido', 422);
            }
            cache()->forget("2fa_verify_{$user->id}");
        }

        $twoFa->update([
            'is_enabled' => true,
            'verified_at' => now(),
        ]);

        $backupCodes = collect(range(1, 8))->map(fn () => Str::random(8))->toArray();
        $twoFa->update([
            'backup_codes' => array_map(fn (string $code) => Hash::make($code), $backupCodes),
        ]);

        return ApiResponse::message('2FA ativado com sucesso', 200, ['backup_codes' => $backupCodes]);
    }

    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::message('Senha incorreta', 403);
        }

        $user->twoFactorAuth?->update(['is_enabled' => false]);

        return ApiResponse::message('2FA desativado');
    }
}
