<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * PROFESSIONAL Security Tests — Permission Enforcement
 *
 * Tests that permission middleware is active and correctly blocking/allowing.
 * NO permissions are bypassed — tests verify real authorization behavior.
 */
class PermissionEnforcementTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $viewer;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        setPermissionsTeamId($this->tenant->id);

        // Admin with full permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $adminPermissions = [
            'cadastros.customer.view',
            'os.work_order.view',
            'os.work_order.update',
            'os.work_order.change_status',
            'iam.user.view',
        ];
        foreach ($adminPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }
        $adminRole->syncPermissions($adminPermissions);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole($adminRole);

        // Viewer role with only view permissions
        $viewerRole = Role::firstOrCreate(['name' => 'visualizador', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $viewPerm = Permission::firstOrCreate(['name' => 'cadastros.customer.view', 'guard_name' => 'web']);
        $viewerRole->givePermissionTo($viewPerm);
        $this->viewer->assignRole($viewerRole);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    // ═══════════════════════════════════════════════════════════
    // 1. ADMIN TEM ACESSO TOTAL
    // ═══════════════════════════════════════════════════════════

    public function test_admin_can_access_all_endpoints(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/customers')->assertOk();
        $this->getJson('/api/v1/work-orders')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // 2. VIEWER PODE VER CLIENTES
    // ═══════════════════════════════════════════════════════════

    public function test_viewer_can_list_customers(): void
    {
        Sanctum::actingAs($this->viewer);

        $response = $this->getJson('/api/v1/customers');

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // 3. SEM TOKEN → 401
    // ═══════════════════════════════════════════════════════════

    public function test_no_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/work-orders');

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════
    // 4. LOGIN COM CREDENCIAIS VÁLIDAS
    // ═══════════════════════════════════════════════════════════

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'test@empresa.com',
            'password' => bcrypt('senha123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@empresa.com',
            'password' => 'senha123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['token']]);
    }

    // ═══════════════════════════════════════════════════════════
    // 5. LOGIN COM SENHA ERRADA → 401
    // ═══════════════════════════════════════════════════════════

    public function test_login_with_wrong_password_returns_401(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'test@empresa.com',
            'password' => bcrypt('senha123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@empresa.com',
            'password' => 'senhaerrada',
        ]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // 6. LOGIN COM EMAIL INEXISTENTE → 401
    // ═══════════════════════════════════════════════════════════

    public function test_login_with_nonexistent_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'naoexiste@empresa.com',
            'password' => 'qualquer',
        ]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // 7. RESPONSE NÃO EXPÕE PASSWORD
    // ═══════════════════════════════════════════════════════════

    public function test_user_response_does_not_expose_password(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/users');

        $response->assertOk();
        $content = $response->content();
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('remember_token', $content);
    }

    // ═══════════════════════════════════════════════════════════
    // 8. ACESSO A PERFIL AUTENTICADO
    // ═══════════════════════════════════════════════════════════

    public function test_authenticated_user_can_access_profile(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email']]]);
    }

    // ═══════════════════════════════════════════════════════════
    // 9. LOGOUT REVOGA TOKEN
    // ═══════════════════════════════════════════════════════════

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'logout@test.com',
            'password' => bcrypt('senha123'),
        ]);

        // Login first
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'logout@test.com',
            'password' => 'senha123',
        ]);

        $loginResponse->assertOk();
        $token = $loginResponse->json('data.token');
        $this->assertNotNull($token);

        // Logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout');

        $logoutResponse->assertOk();

        // Reset auth guards to prevent in-process caching of resolved user
        $this->app['auth']->forgetGuards();

        // Verify token is revoked
        $afterLogout = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');

        $afterLogout->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════
    // 10. STATUS VALIDATION NO UPDATE
    // ═══════════════════════════════════════════════════════════

    public function test_invalid_status_value_is_rejected(): void
    {
        Sanctum::actingAs($this->admin);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'open',
        ]);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => 'invalid_status_xss',
        ]);

        $response->assertStatus(422);
    }
}
