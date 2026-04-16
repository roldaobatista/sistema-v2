<?php

namespace Tests\Feature\Flow400;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckReportExportPermission;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Fluxo 4: Criar Role 'Técnico de Calibração' com permissões restritas (OS + Metrologia).
 * Criar usuário com essa role e validar que não acessa Financeiro (403).
 */
class Flow004RoleUsuarioRestritoTest extends TestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware([
            CheckPermission::class,
            CheckReportExportPermission::class,
        ]);
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();
        Branch::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'MTZ'],
            ['name' => 'Matriz']
        );

        setPermissionsTeamId($this->tenant->id);
        foreach (['os.work_order.view', 'metrology.certificate.view', 'finance.receivable.view'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function test_fluxo4_criar_role_e_usuario_tecnico_financeiro_retorna_403(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $role = Role::create(['name' => 'tecnico_calibracao', 'guard_name' => 'web']);
        $role->givePermissionTo(['os.work_order.view', 'metrology.certificate.view']);

        $user = User::factory()->create([
            'name' => 'Carlos Técnico',
            'email' => 'carlos.tecnico@flow4.test',
            'password' => Hash::make('Password123'),
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $user->tenants()->sync([$this->tenant->id => ['is_default' => true]]);
        $user->assignRole($role);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'carlos.tecnico@flow4.test',
            'password' => 'Password123',
        ]);
        $loginResponse->assertOk();
        $token = $loginResponse->json('data.token');

        $receivableResponse = $this->getJson('/api/v1/accounts-receivable', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $receivableResponse->assertStatus(403);
    }
}
