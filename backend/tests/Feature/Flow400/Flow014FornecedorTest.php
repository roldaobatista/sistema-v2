<?php

namespace Tests\Feature\Flow400;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Fluxo 14: Cadastrar Fornecedor (Distribuidora de Metais ABC, CNPJ).
 */
class Flow014FornecedorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MTZ'],
            ['name' => 'Matriz']
        );

        $role = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['display_name' => 'Super Administrador']
        );

        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@flow14.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->sync([$tenant->id => ['is_default' => true]]);
        setPermissionsTeamId($tenant->id);
        Permission::firstOrCreate(['name' => 'cadastros.supplier.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cadastros.supplier.view', 'guard_name' => 'web']);
        $role->givePermissionTo(['cadastros.supplier.create', 'cadastros.supplier.view']);
        $user->assignRole($role);
    }

    public function test_fluxo14_cadastrar_fornecedor_pj_retorna_201(): void
    {
        $login = $this->postJson('/api/v1/login', ['email' => 'admin@flow14.test', 'password' => 'password']);
        $login->assertOk();
        $token = $login->json('data.token');

        $payload = [
            'type' => 'PJ',
            'name' => 'Distribuidora de Metais ABC',
            'document' => '44.332.211/0001-55',
            'email' => 'vendas@metaisabc.com.br',
        ];

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/suppliers', $payload);

        $res->assertStatus(201);
        $res->assertJsonPath('data.name', 'Distribuidora de Metais ABC');
        $res->assertJsonPath('data.type', 'PJ');
        $id = $res->json('data.id');
        $this->assertArrayHasKey('id', $res->json('data'));

        // Fluxo completo: persistência no banco
        $this->assertDatabaseHas('suppliers', [
            'id' => $id,
            'name' => 'Distribuidora de Metais ABC',
            'type' => 'PJ',
            'document' => '44.332.211/0001-55',
        ]);
    }
}
