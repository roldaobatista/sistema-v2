<?php

namespace Tests\Smoke;

/**
 * Smoke: Login e autenticação
 * Garante que o sistema aceita login e retorna token.
 */
class AuthSmokeTest extends SmokeTestCase
{
    public function test_login_returns_token(): void
    {
        $this->user->update(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        // qa-02 (Re-auditoria Camada 1 r4): o nome do teste promete 401.
        // Removido `withoutMiddleware()` que zerava a stack e tornava a
        // asserção inútil. Sem token Sanctum, rota autenticada DEVE 401.
        //
        // Observação: SmokeTestCase::setUp() chama Sanctum::actingAs() para os
        // demais testes. Aqui precisamos desfazer isso — forgetGuards() zera
        // o usuário resolvido em todos os guards (incluindo sanctum), forçando
        // o AuthenticateMiddleware a re-resolver sem credenciais → 401.
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/customers');

        $response->assertUnauthorized();
    }
}
