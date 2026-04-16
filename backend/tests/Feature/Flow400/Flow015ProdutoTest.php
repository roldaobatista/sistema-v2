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
 * Fluxo 15: Cadastrar Produto (Balança Rodoviária 80t, preço).
 */
class Flow015ProdutoTest extends TestCase
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
            'email' => 'admin@flow15.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->sync([$tenant->id => ['is_default' => true]]);
        setPermissionsTeamId($tenant->id);
        Permission::firstOrCreate(['name' => 'cadastros.product.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cadastros.product.view', 'guard_name' => 'web']);
        $role->givePermissionTo(['cadastros.product.create', 'cadastros.product.view']);
        $user->assignRole($role);
    }

    public function test_fluxo15_cadastrar_produto_retorna_201(): void
    {
        $login = $this->postJson('/api/v1/login', ['email' => 'admin@flow15.test', 'password' => 'password']);
        $login->assertOk();
        $token = $login->json('data.token');

        $payload = [
            'name' => 'Balança Rodoviária 80t',
            'sell_price' => 185000,
            'cost_price' => 120000,
        ];

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/products', $payload);

        $res->assertStatus(201);
        $res->assertJsonPath('data.name', 'Balança Rodoviária 80t');
        $id = $res->json('data.id');
        $this->assertArrayHasKey('id', $res->json('data'));

        // Fluxo completo: persistência no banco
        $this->assertDatabaseHas('products', [
            'id' => $id,
            'name' => 'Balança Rodoviária 80t',
        ]);
    }
}
