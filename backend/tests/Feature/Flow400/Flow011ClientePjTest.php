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
 * Fluxo 11: Cadastrar Cliente PJ completo (Metalurgica Rossi, CNPJ, contato).
 */
class Flow011ClientePjTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create(['name' => 'Empresa Principal']);
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
            'email' => 'admin@flow11.test',
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

    public function test_fluxo11_cadastrar_cliente_pj_retorna_201(): void
    {
        $login = $this->postJson('/api/v1/login', ['email' => 'admin@flow11.test', 'password' => 'password']);
        $login->assertOk();
        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $payload = [
            'type' => 'PJ',
            'name' => 'Metalurgica Rossi',
            'trade_name' => 'Rossi',
            'document' => '33.000.167/0001-01',
            'email' => 'contato@metalurgicarossi.com.br',
            'phone' => '6633334444',
            'address_city' => 'Rondonópolis',
            'address_state' => 'MT',
            'contacts' => [
                ['name' => 'Roberto Rossi', 'role' => 'Diretor', 'email' => 'roberto@metalurgicarossi.com.br', 'is_primary' => true],
            ],
        ];

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customers', $payload);

        $res->assertStatus(201);
        $res->assertJsonPath('data.name', 'Metalurgica Rossi');
        $res->assertJsonPath('data.type', 'PJ');
        $res->assertJsonPath('data.document', '33.000.167/0001-01');
        $id = $res->json('data.id');
        $this->assertArrayHasKey('id', $res->json('data'));

        // Fluxo completo: persistência no banco.
        // Wave 1B: `customers.document` é encrypted — comparar via `document_hash`.
        $this->assertDatabaseHas('customers', [
            'id' => $id,
            'name' => 'Metalurgica Rossi',
            'type' => 'PJ',
            'document_hash' => Customer::hashSearchable('33.000.167/0001-01', digitsOnly: true),
        ]);
        $this->assertDatabaseHas('customer_contacts', [
            'customer_id' => $id,
            'name' => 'Roberto Rossi',
        ]);
    }
}
