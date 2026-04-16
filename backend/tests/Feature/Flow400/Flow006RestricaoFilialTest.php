<?php

namespace Tests\Feature\Flow400;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fluxo 6: Restrição de acesso por filial. Configurar usuário para filial (ex: Rondonópolis).
 */
class Flow006RestricaoFilialTest extends TestCase
{
    public function test_fluxo6_listar_filiais_e_usuario_com_branch(): void
    {
        $tenant = Tenant::factory()->create();
        $matriz = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MTZ'],
            ['name' => 'Matriz']
        );
        $rondonopolis = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'RDP'],
            ['name' => 'Filial Rondonópolis']
        );

        $user = User::factory()->create([
            'email' => 'user@flow6.test',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'branch_id' => $rondonopolis->id,
        ]);
        $user->tenants()->sync([$tenant->id => ['is_default' => true]]);

        $this->assertNotNull($user->branch_id);
        $this->assertEquals($rondonopolis->id, $user->branch_id);
    }
}
