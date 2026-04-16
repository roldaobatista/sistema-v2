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
 * Fluxo 13: Listar Clientes — GET /customers retorna lista paginada.
 */
class Flow013ListarClientesTest extends TestCase
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
            'email' => 'admin@flow13.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->sync([$tenant->id => ['is_default' => true]]);
        setPermissionsTeamId($tenant->id);
        Permission::firstOrCreate(['name' => 'cadastros.customer.view', 'guard_name' => 'web']);
        $role->givePermissionTo('cadastros.customer.view');
        $user->assignRole($role);

        Customer::factory()->count(3)->create(['tenant_id' => $tenant->id]);
    }

    public function test_fluxo13_listar_clientes_retorna_200_e_lista(): void
    {
        $login = $this->postJson('/api/v1/login', ['email' => 'admin@flow13.test', 'password' => 'password']);
        $login->assertOk();
        $token = $login->json('data.token');

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/customers');

        $res->assertStatus(200);
        $res->assertJsonStructure(['data', 'meta' => ['current_page', 'total']]);
        $this->assertGreaterThanOrEqual(3, (int) $res->json('meta.total'));
    }
}
