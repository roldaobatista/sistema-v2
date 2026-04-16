<?php

namespace Tests\Feature\Flow400;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Fluxo 3: Execute o fluxo de recuperação de senha: solicite o reset, capture o token
 * do banco local, insira a nova senha e tente logar novamente.
 */
class Flow003RecuperacaoSenhaTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => Hash::make('oldpassword'),
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
    }

    public function test_fluxo3_solicitar_reset_retorna_200(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'user@test.com',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('cadastrado', $response->json('message'));
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@test.com',
        ]);
    }

    public function test_fluxo3_reset_com_token_e_login_com_nova_senha(): void
    {
        $token = Password::createToken($this->user);

        $resetResponse = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@test.com',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $resetResponse->assertOk();
        $this->assertStringContainsString('redefinida', $resetResponse->json('message'));

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'user@test.com',
            'password' => 'NewPassword123',
        ]);

        $loginResponse->assertOk();
        $this->assertNotEmpty($loginResponse->json('data.token'));
        $this->assertEquals($this->user->id, $loginResponse->json('data.user.id'));
    }
}
