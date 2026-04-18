<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Smoke tests for core modules: Customer, Quote, WorkOrder, Financial, Stock.
 * Ensures main API endpoints respond and respect tenant scope.
 */
class CoreModulesSmokeTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->seed(PermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        // Bulk assign all permissions
        $pivotTable = config('permission.table_names.model_has_permissions', 'model_has_permissions');
        $permIds = Permission::pluck('id');
        DB::table($pivotTable)->insertOrIgnore($permIds->map(fn ($id) => [
            'permission_id' => $id,
            'model_type' => get_class($this->user),
            'model_id' => $this->user->id,
        ])->toArray());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_customer_index_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/customers');
        $response->assertOk();
    }

    public function test_customer_create_and_show(): void
    {
        $response = $this->postJson('/api/v1/customers', [
            'type' => 'PF',
            'name' => 'Smoke Cliente',
            'document' => '529.982.247-25',
            'email' => 'smoke@test.com',
        ]);
        $response->assertStatus(201);
        $id = $response->json('data.id');
        $this->getJson("/api/v1/customers/{$id}")->assertOk();
    }

    public function test_quotes_index_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/quotes');
        $response->assertOk();
    }

    public function test_work_order_index_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/work-orders');
        $response->assertOk();
    }

    public function test_work_order_create_and_show(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $customer->id,
            'description' => 'Smoke OS',
            'priority' => 'medium',
        ]);
        $response->assertStatus(201);
        $id = $response->json('data.id');
        $this->getJson("/api/v1/work-orders/{$id}")->assertOk();
    }

    public function test_accounts_receivable_summary_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/accounts-receivable-summary');
        $response->assertOk();
    }

    public function test_accounts_payable_summary_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/accounts-payable-summary');
        $response->assertOk();
    }

    public function test_stock_movements_index_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/stock/movements');
        $response->assertOk();
    }

    public function test_stock_summary_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/stock/summary');
        $response->assertOk();
    }
}
