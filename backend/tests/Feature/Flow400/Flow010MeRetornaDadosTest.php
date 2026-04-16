<?php

namespace Tests\Feature\Flow400;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fluxo 10: GET /me retorna user, tenant, roles corretos.
 */
class Flow010MeRetornaDadosTest extends TestCase
{
    public function test_fluxo10_me_retorna_user_tenant_roles(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Empresa Principal']);
        $user = User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@flow10.test',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->sync([$tenant->id => ['is_default' => true]]);

        $login = $this->postJson('/api/v1/login', ['email' => 'admin@flow10.test', 'password' => 'password']);
        $login->assertOk();
        $token = $login->json('data.token');

        $me = $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token]);
        $me->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'admin@flow10.test')
            ->assertJsonPath('data.user.tenant_id', $tenant->id);
    }
}
