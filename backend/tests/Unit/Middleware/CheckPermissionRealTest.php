<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckPermission;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Testes profundos do CheckPermission middleware real.
 */
class CheckPermissionRealTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $regular;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('admin');

        $this->regular = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->regular->tenants()->attach($this->tenant->id, ['is_default' => true]);

    }

    public function test_admin_can_access_any_route(): void
    {
        // qa-02 (Re-auditoria Camada 1 r4): nome do teste = "CAN access".
        // Logo admin DEVE acessar 200. Para isso, seed de permissions é
        // obrigatório — sem seed, role admin não carrega permissões e
        // middleware CheckPermission retorna 403 (contradição com nome).
        $this->seedPermissionsForAdmin();
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers');
        $response->assertOk();
    }

    public function test_unauthenticated_blocked(): void
    {
        $response = $this->getJson('/api/v1/customers');
        $response->assertUnauthorized();
    }

    public function test_middleware_instance(): void
    {
        $middleware = new CheckPermission;
        $this->assertInstanceOf(CheckPermission::class, $middleware);
    }

    public function test_admin_accesses_settings(): void
    {
        $this->seedPermissionsForAdmin();
        $response = $this->actingAs($this->admin)->getJson('/api/v1/settings');
        $response->assertOk();
    }

    public function test_admin_accesses_users(): void
    {
        $this->seedPermissionsForAdmin();
        $response = $this->actingAs($this->admin)->getJson('/api/v1/users');
        $response->assertOk();
    }

    public function test_admin_accesses_roles(): void
    {
        $this->seedPermissionsForAdmin();
        $response = $this->actingAs($this->admin)->getJson('/api/v1/roles');
        $response->assertOk();
    }

    private function seedPermissionsForAdmin(): void
    {
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        // Re-assign role after seeding to pick up permissions
        $this->admin->unsetRelation('roles')->unsetRelation('permissions');
    }
}
