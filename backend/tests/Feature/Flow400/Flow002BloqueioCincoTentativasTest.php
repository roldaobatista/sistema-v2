<?php

namespace Tests\Feature\Flow400;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fluxo 2: Teste o bloqueio de conta após 5 tentativas consecutivas de login
 * com senha incorreta, validando a mensagem de erro de 'Conta bloqueada'.
 */
class Flow002BloqueioCincoTentativasTest extends TestCase
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
            'password' => Hash::make('password'),
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
    }

    public function test_fluxo2_primeiras_cinco_tentativas_retornam_422(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/login', [
                'email' => 'user@test.com',
                'password' => 'wrong',
            ]);
            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['email']);
        }
    }

    public function test_fluxo2_sexta_tentativa_retorna_429_conta_bloqueada(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'user@test.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/v1/login', [
            'email' => 'user@test.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
        $message = $response->json('message');
        $this->assertNotEmpty($message);
        $this->assertStringContainsString('Conta bloqueada', $message, 'Mensagem deve conter "Conta bloqueada" conforme spec do Fluxo 2.');
    }
}
