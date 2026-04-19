<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdditionalControllersTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    // ── Products CRUD Deep ──

    public function test_products_show(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->user)->getJson("/api/v1/products/{$p->id}");
        $response->assertOk();
    }

    public function test_products_update(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->user)->putJson("/api/v1/products/{$p->id}", [
            'name' => 'Produto Atualizado',
        ]);
        $response->assertOk();
    }

    public function test_products_destroy(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->user)->deleteJson("/api/v1/products/{$p->id}");
        $response->assertNoContent();
    }

    public function test_products_search(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Peso Padrão Search']);
        $response = $this->actingAs($this->user)->getJson('/api/v1/products?search=Peso+Padrão');
        $response->assertOk();
    }

    // ── Expenses CRUD Deep ──

    public function test_expenses_show(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        $response = $this->actingAs($this->user)->getJson("/api/v1/expenses/{$exp->id}");
        $response->assertOk();
    }

    public function test_expenses_update(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        $response = $this->actingAs($this->user)->putJson("/api/v1/expenses/{$exp->id}", [
            'description' => 'Atualizado',
        ]);
        $response->assertOk();
    }

    public function test_expenses_destroy(): void
    {
        $exp = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        $response = $this->actingAs($this->user)->deleteJson("/api/v1/expenses/{$exp->id}");
        $response->assertNoContent();
    }

    public function test_expenses_filter_by_status(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/expenses?status=pending');
        $response->assertOk();
    }

    // ── ServiceCalls CRUD Deep ──

    public function test_service_calls_show(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->user)->getJson("/api/v1/service-calls/{$sc->id}");
        $response->assertOk();
    }

    public function test_service_calls_update(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->user)->putJson("/api/v1/service-calls/{$sc->id}", [
            'subject' => 'Atualizado',
        ]);
        $response->assertOk();
    }

    public function test_service_calls_destroy(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->user)->deleteJson("/api/v1/service-calls/{$sc->id}");
        $response->assertNoContent();
    }

    // ── Warehouses ──

    public function test_warehouses_index(): void
    {
        Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->user)->getJson('/api/v1/warehouses');
        $response->assertOk();
    }

    public function test_warehouses_store(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/warehouses', [
            'name' => 'Almoxarifado Central',
            'is_main' => true,
        ]);
        $response->assertCreated();
    }

    // ── Stock Movements ──

    public function test_stock_movements_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/stock-movements');
        $response->assertOk();
    }

    public function test_stock_movement_entry(): void
    {
        $p = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $w = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/stock-movements', [
            'product_id' => $p->id,
            'warehouse_id' => $w->id,
            'type' => 'entry',
            'quantity' => 100,
            'reason' => 'Compra',
        ]);
        $response->assertCreated();
    }

    // ── Expense Categories ──

    public function test_expense_categories_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/expense-categories');
        $response->assertOk();
    }

    public function test_expense_categories_store(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/expense-categories', [
            'name' => 'Combustível',
            'monthly_budget' => '5000.00',
        ]);
        $response->assertCreated();
    }

    // ── Reports ──

    public function test_reports_work_orders(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/reports/work-orders');
        $response->assertOk();
    }

    public function test_reports_financial(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/reports/financial');
        $response->assertOk();
    }

    // ── Import ──

    public function test_imports_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/imports');
        $response->assertOk();
    }

    // ── Audit Log ──

    public function test_audit_log_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/audit-logs');
        $response->assertOk();
    }

    // ── Users ──

    public function test_users_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/users');
        $response->assertOk();
    }

    // ── Tenant Settings ──

    public function test_tenant_settings_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/settings');
        $response->assertOk();
    }
}
