<?php

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * sec-07: Password reset hardening.
 *
 * Ao redefinir senha via token "esqueci minha senha":
 *  - password_changed_at deve ser atualizado (OWASP ASVS V2.1.10).
 *  - Tokens Sanctum do usuario devem ser revogados (ja existente).
 *  - Sessoes web (guard stateful) devem ser invalidadas via logoutOtherDevices.
 */
class PasswordResetHardeningTest extends TestCase
{
    public function test_password_reset_updates_password_changed_at(): void
    {
        $this->assertTrue(
            Schema::hasColumn('users', 'password_changed_at'),
            'users.password_changed_at nao existe — migration sec-07 nao aplicada.'
        );

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'email' => 'sec07@example.com',
            'password_changed_at' => null,
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'sec07@example.com',
            'password' => 'NovaSenha@Segura123!',
            'password_confirmation' => 'NovaSenha@Segura123!',
        ]);

        $response->assertStatus(200);

        $user->refresh();

        $this->assertNotNull(
            $user->password_changed_at,
            'password_changed_at nao foi atualizado apos reset de senha.'
        );
        $this->assertTrue(
            $user->password_changed_at->greaterThan(now()->subMinute()),
            'password_changed_at nao reflete instante recente do reset.'
        );
    }

    public function test_password_reset_revokes_all_sanctum_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'sec07-tokens@example.com',
        ]);

        $user->createToken('api', ['*']);
        $user->createToken('mobile', ['*']);
        $this->assertSame(2, $user->tokens()->count());

        $token = Password::createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'sec07-tokens@example.com',
            'password' => 'NovaSenha@Segura123!',
            'password_confirmation' => 'NovaSenha@Segura123!',
        ])->assertStatus(200);

        $this->assertSame(
            0,
            $user->tokens()->count(),
            'Tokens Sanctum nao foram revogados apos reset.'
        );
    }
}
