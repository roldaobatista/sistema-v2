<?php

namespace Tests\Feature\Flow400;

use Tests\TestCase;

/**
 * Fluxo 8: Token inválido/expirado → GET /me retorna 401.
 */
class Flow008TokenInvalidoTest extends TestCase
{
    public function test_fluxo8_token_invalido_retorna_401(): void
    {
        $response = $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer invalid_token_xyz',
        ]);
        $response->assertStatus(401);
    }
}
