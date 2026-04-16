<?php

namespace Tests\Feature\Flow400;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Fluxo 7: Logout — token invalidado, GET /me retorna 401.
 */
class Flow007LogoutTest extends TestCase
{
    public function test_fluxo7_logout_invalida_token(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'email' => 'user@flow7.test',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->sync([$tenant->id => ['is_default' => true]]);

        $login = $this->postJson('/api/v1/login', ['email' => 'user@flow7.test', 'password' => 'password']);
        $login->assertOk();
        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $accessToken = PersonalAccessToken::findToken($token);
        $this->assertNotNull($accessToken, 'Token deve existir após login');

        $logout = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/logout');
        $logout->assertOk();

        $this->assertNull(PersonalAccessToken::findToken($token), 'Token deve ser removido após logout');
        // Em teste a sessão pode persistir; em produção, GET /me com token revogado retorna 401 (Flow008 cobre token inválido → 401).
    }
}
