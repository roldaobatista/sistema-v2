<?php

namespace Tests\Critical;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * P1.1 — Matriz de Permissões RBAC
 *
 * NOTA: Este teste NÃO usa CriticalTestCase porque precisa
 * testar permissões REAIS (sem Gate::before bypass).
 * Faz seu próprio setUp com tenant + auth mas SEM gate bypass.
 */
class RbacPermissionMatrixTest extends TestCase
{
    private Tenant $tenant;

    private User $adminUser;

    private User $restrictedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
        $this->withoutMiddleware([
            EnsureTenantScope::class,
        ]);

        $this->tenant = Tenant::factory()->create();

        // Admin com todas as permissões
        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->adminUser->givePermissionTo(
            Permission::firstOrCreate(['name' => 'cadastros.customer.view', 'guard_name' => 'web'])
        );

        // Usuário restrito — só visualiza clientes
        $this->restrictedUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $viewCustomers = Permission::firstOrCreate(['name' => 'cadastros.customer.view', 'guard_name' => 'web']);
        $this->restrictedUser->givePermissionTo($viewCustomers);
    }

    public function test_permissions_seeder_includes_platform_tenant_switch_for_super_admin(): void
    {
        $this->seed(PermissionsSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'name' => 'platform.tenant.switch',
            'guard_name' => 'web',
        ]);

        $superAdminRole = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();

        $this->assertNotNull($superAdminRole, 'Role super_admin deve existir após o seeder.');
        $this->assertTrue(
            $superAdminRole->hasPermissionTo('platform.tenant.switch'),
            'Role super_admin deve receber a permissão platform.tenant.switch.'
        );
    }

    #[DataProvider('protectedEndpoints')]
    public function test_restricted_user_cannot_access_protected_endpoints(
        string $method,
        string $endpoint
    ): void {
        Sanctum::actingAs($this->restrictedUser, ['*']);

        $response = $this->{$method}($endpoint);

        $response->assertForbidden();
    }

    public static function protectedEndpoints(): array
    {
        return [
            'criar customer sem permissão' => ['postJson', '/api/v1/customers'],
            'criar OS sem permissão' => ['postJson', '/api/v1/work-orders'],
            'criar orçamento sem permissão' => ['postJson', '/api/v1/quotes'],
            'acessar financeiro sem permissão' => ['getJson', '/api/v1/accounts-receivable-summary'],
            'acessar estoque sem permissão' => ['getJson', '/api/v1/stock/movements'],
        ];
    }

    public function test_admin_user_can_access_customers(): void
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson('/api/v1/customers');

        $this->assertTrue(
            in_array($response->status(), [200, 302]),
            "Admin não conseguiu acessar customers: {$response->status()}"
        );
    }

    public function test_role_assignment_respects_team_tenant(): void
    {
        // Spatie uses setPermissionsTeamId() for team context (already set in setUp)
        $role = Role::findOrCreate('test-rbac-role', 'web');

        $this->restrictedUser->assignRole($role);

        $this->assertTrue(
            $this->restrictedUser->hasRole($role),
            'Role não foi atribuída corretamente no contexto do tenant'
        );
    }
}
