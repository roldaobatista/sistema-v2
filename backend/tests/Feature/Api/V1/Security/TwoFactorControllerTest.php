<?php

namespace Tests\Feature\Api\V1\Security;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwoFactorControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'password' => Hash::make('SenhaForte123!'),
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_status_returns_disabled_by_default(): void
    {
        // Route: security/2fa/status (dentro do prefix('security') no advanced-lots.php)
        $response = $this->getJson('/api/v1/security/2fa/status');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['enabled', 'method', 'verified_at']])
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.method', null);
    }

    public function test_status_reflects_enabled_two_factor_auth(): void
    {
        // Cria registro 2FA ativo para o user
        $this->user->twoFactorAuth()->create([
            'method' => 'email',
            'secret' => encrypt('test-secret'),
            'is_enabled' => true,
            'verified_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/security/2fa/status');

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.method', 'email');
    }

    public function test_status_isolates_two_factor_auth_per_user(): void
    {
        // Outro user do mesmo tenant com 2FA ativo
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $otherUser->twoFactorAuth()->create([
            'method' => 'app',
            'secret' => encrypt('other-secret'),
            'is_enabled' => true,
            'verified_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        // O user atual nao tem 2FA — status deve retornar disabled
        $response = $this->getJson('/api/v1/security/2fa/status');

        $response->assertOk()
            ->assertJsonPath('data.enabled', false, 'Status de 2FA vazou de outro user');
    }

    public function test_enable_endpoints_are_not_routed_by_design(): void
    {
        // 2FA enable/verify/disable estao DESATIVADOS POR DECISAO DO PROPRIETARIO
        // conforme comentario em backend/routes/api/advanced-lots.php:291.
        // Este teste documenta essa decisao de arquitetura e falhara se alguem
        // registrar as rotas sem atualizar o teste + remover o comentario.

        $this->postJson('/api/v1/security/2fa/enable', [
            'method' => 'email',
            'password' => 'test',
        ])->assertStatus(404);

        $this->postJson('/api/v1/security/2fa/verify', ['code' => '123456'])
            ->assertStatus(404);

        $this->postJson('/api/v1/security/2fa/disable', ['password' => 'test'])
            ->assertStatus(404);
    }
}
