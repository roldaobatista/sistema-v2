<?php

namespace Tests\Feature\Flow400;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fluxo 1: Simule o fluxo de login com sucesso utilizando credenciais válidas
 * e valide o redirecionamento correto com base na role do usuário.
 */
class Flow001LoginTest extends TestCase
{
    private string $loginEmail;

    private string $loginPassword;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginEmail = 'admin@example.test';
        $this->loginPassword = bin2hex(random_bytes(16));

        $tenant = Tenant::factory()->create([
            'name' => 'Empresa Principal',
            'document' => '00.000.000/0001-00',
        ]);

        Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MTZ'],
            ['name' => 'Matriz']
        );

        $role = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['display_name' => 'Super Administrador']
        );

        $user = User::factory()->create([
            'name' => 'Administrador',
            'email' => $this->loginEmail,
            'password' => Hash::make($this->loginPassword),
            'is_active' => true,
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->sync([$tenant->id => ['is_default' => true]]);

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role);
    }

    public function test_fluxo1_login_com_sucesso_retorna_token_e_user_com_roles(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => $this->loginEmail,
            'password' => $this->loginPassword,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'tenant_id', 'permissions', 'roles', 'role_details'],
                ],
            ]);

        $payload = $response->json('data');
        $this->assertNotEmpty($payload['token']);
        $this->assertIsArray($payload['user']['roles']);
        $this->assertContains('super_admin', $payload['user']['roles'], 'Resposta do login deve conter role super_admin para redirecionamento correto.');
    }

    public function test_fluxo1_me_com_token_retorna_user_e_roles(): void
    {
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $this->loginEmail,
            'password' => $this->loginPassword,
        ]);
        $loginResponse->assertOk();
        $token = $loginResponse->json('data.token');

        $meResponse = $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $meResponse->assertOk()
            ->assertJsonPath('data.user.email', $this->loginEmail);
        $roles = $meResponse->json('data.user.roles');
        $this->assertIsArray($roles);
        $this->assertContains('super_admin', $roles);
    }
}
