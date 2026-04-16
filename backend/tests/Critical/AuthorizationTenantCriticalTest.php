<?php

namespace Tests\Critical;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuthorizationTenantCriticalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([EnsureTenantScope::class]);
    }

    public function test_user_without_receivable_create_permission_cannot_generate_financial_title(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, ['finance.receivable.view']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'total' => 500,
        ]);

        $this->actingAsTenantUser($user, $tenant);

        $response = $this->postJson('/api/v1/accounts-receivable/generate-from-os', [
            'work_order_id' => $workOrder->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'payment_method' => 'pix',
        ]);

        $response->assertForbidden();
    }

    public function test_account_receivable_index_only_returns_records_from_current_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = $this->createTenantUser($tenantA, ['finance.receivable.view']);
        $userB = $this->createTenantUser($tenantB, ['finance.receivable.view']);

        $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $ownReceivable = AccountReceivable::factory()->create([
            'tenant_id' => $tenantA->id,
            'customer_id' => $customerA->id,
            'created_by' => $userA->id,
            'description' => 'Recebível Tenant A',
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $tenantB->id,
            'customer_id' => $customerB->id,
            'created_by' => $userB->id,
            'description' => 'Recebível Tenant B',
        ]);

        $this->actingAsTenantUser($userA, $tenantA);

        $response = $this->getJson('/api/v1/accounts-receivable');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownReceivable->id)
            ->assertJsonPath('data.0.description', 'Recebível Tenant A');
    }

    public function test_customer_show_returns_404_for_other_tenant_even_with_view_permission(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $viewer = $this->createTenantUser($tenantA, ['cadastros.customer.view']);
        $foreignCustomer = Customer::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Cliente Tenant B',
        ]);

        $this->actingAsTenantUser($viewer, $tenantA);

        $response = $this->getJson("/api/v1/customers/{$foreignCustomer->id}");

        $response->assertNotFound();
    }

    private function createTenantUser(Tenant $tenant, array $permissions): User
    {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        setPermissionsTeamId($tenant->id);

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    private function actingAsTenantUser(User $user, Tenant $tenant): void
    {
        app()->instance('current_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        Sanctum::actingAs($user, ['*']);
    }
}
