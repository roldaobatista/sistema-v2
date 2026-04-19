<?php

namespace Tests\Feature\Flow400;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Fluxo 12: Cadastrar Cliente PF (Maria Silva Santos, CPF).
 */
class Flow012ClientePfTest extends TestCase
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
            'email' => 'admin@flow12.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->sync([$tenant->id => ['is_default' => true]]);
        setPermissionsTeamId($tenant->id);
        Permission::firstOrCreate(['name' => 'cadastros.customer.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cadastros.customer.view', 'guard_name' => 'web']);
        $role->givePermissionTo(['cadastros.customer.create', 'cadastros.customer.view']);
        $user->assignRole($role);
    }

    public function test_fluxo12_cadastrar_cliente_pf_retorna_201(): void
    {
        $login = $this->postJson('/api/v1/login', ['email' => 'admin@flow12.test', 'password' => 'password']);
        $login->assertOk();
        $token = $login->json('data.token');

        $payload = [
            'type' => 'PF',
            'name' => 'Maria Silva Santos',
            'document' => '529.982.247-25',
            'email' => 'maria.silva@email.com',
            'address_city' => 'Rondonópolis',
            'address_state' => 'MT',
        ];

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customers', $payload);

        $res->assertStatus(201);
        $res->assertJsonPath('data.name', 'Maria Silva Santos');
        $res->assertJsonPath('data.type', 'PF');
        $res->assertJsonPath('data.document', '529.982.247-25');
        $id = $res->json('data.id');
        $this->assertArrayHasKey('id', $res->json('data'));

        // Fluxo completo: persistência no banco.
        // Wave 1B: `customers.document` é encrypted — comparar via `document_hash`.
        $this->assertDatabaseHas('customers', [
            'id' => $id,
            'name' => 'Maria Silva Santos',
            'type' => 'PF',
            'document_hash' => Customer::hashSearchable('529.982.247-25', digitsOnly: true),
        ]);
    }
}
