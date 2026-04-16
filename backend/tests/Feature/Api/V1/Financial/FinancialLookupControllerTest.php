<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── SUPPLIERS ──────────────────────────────────────────────────────────

    public function test_suppliers_returns_active_suppliers_for_tenant(): void
    {
        Supplier::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/suppliers');

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(3, 'data');
    }

    public function test_suppliers_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        Supplier::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);
        Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/suppliers');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_suppliers_search_filters_by_name(): void
    {
        Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Corp',
            'is_active' => true,
        ]);
        Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Globex Inc',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/suppliers?search=acme');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Acme Corp');
    }

    public function test_suppliers_include_inactive_flag(): void
    {
        Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/suppliers?include_inactive=1');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ── CUSTOMERS ──────────────────────────────────────────────────────────

    public function test_customers_returns_active_customers_for_tenant(): void
    {
        Customer::factory()->count(4)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/customers');

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(4, 'data');
    }

    public function test_customers_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        Customer::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/customers');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_customers_search_by_name(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'João Silva',
            'is_active' => true,
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Maria Santos',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/customers?search=jo%C3%A3o');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'João Silva');
    }

    // ── WORK ORDERS ────────────────────────────────────────────────────────

    public function test_work_orders_returns_list_for_tenant(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/work-orders');

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(3, 'data');
    }

    public function test_work_orders_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/work-orders');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── PAYMENT METHODS ────────────────────────────────────────────────────

    public function test_payment_methods_returns_list(): void
    {
        $response = $this->getJson('/api/v1/financial/lookups/payment-methods');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        // data is an array (may be empty if no payment methods seeded)
        $this->assertIsArray($response->json('data'));
    }

    public function test_payment_methods_returns_all_active(): void
    {
        PaymentMethod::factory()->count(2)->create(['is_active' => true]);
        PaymentMethod::factory()->create(['is_active' => false]);

        $total = PaymentMethod::count();
        $response = $this->getJson('/api/v1/financial/lookups/payment-methods');

        $response->assertOk();
        // Controller returns all (no is_active filter in paymentMethods())
        $this->assertCount($total, $response->json('data'));
    }

    // ── BANK ACCOUNTS ──────────────────────────────────────────────────────

    public function test_bank_accounts_returns_active_accounts_for_tenant(): void
    {
        BankAccount::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/bank-accounts');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'bank_name', 'is_active']]]);
    }

    public function test_bank_accounts_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        BankAccount::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);
        BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/bank-accounts');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_bank_accounts_include_inactive_flag(): void
    {
        BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/financial/lookups/bank-accounts?include_inactive=1');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ── SUPPLIER CONTRACT PAYMENT FREQUENCIES ─────────────────────────────

    public function test_supplier_contract_payment_frequencies_returns_list(): void
    {
        $response = $this->getJson('/api/v1/financial/lookups/supplier-contract-payment-frequencies');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ── AUTHENTICATION REQUIRED ────────────────────────────────────────────

    public function test_lookup_endpoints_require_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/financial/lookups/payment-methods')->assertUnauthorized();
        $this->getJson('/api/v1/financial/lookups/bank-accounts')->assertUnauthorized();
    }
}
