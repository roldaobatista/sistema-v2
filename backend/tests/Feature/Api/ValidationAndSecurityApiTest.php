<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes de validação de input, paginação, filtros avançados,
 * e cross-tenant security para todas as entidades principais.
 */
class ValidationAndSecurityApiTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $other;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->other = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    // ═══ Customer Validation ═══

    public function test_customer_store_missing_name(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/customers', []);
        $response->assertStatus(422);
    }

    public function test_customer_store_invalid_email(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/customers', [
            'name' => 'Teste',
            'email' => 'invalid-email',
        ]);
        $response->assertStatus(422);
    }

    // ═══ WO Validation ═══

    public function test_wo_store_missing_customer(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/work-orders', []);
        $response->assertStatus(422);
    }

    // ═══ Equipment Validation ═══

    public function test_equipment_store_missing_fields(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/equipments', []);
        $response->assertStatus(422);
    }

    // ═══ Pagination ═══

    public function test_customers_pagination(): void
    {
        Customer::factory()->count(25)->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers?page=1&per_page=10');
        $response->assertOk();
    }

    public function test_customers_pagination_page_2(): void
    {
        Customer::factory()->count(25)->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers?page=2&per_page=10');
        $response->assertOk();
    }

    public function test_wo_pagination(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders?page=1&per_page=5');
        $response->assertOk();
    }

    // ═══ Search ═══

    public function test_customer_search(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Empresa Única XYZ',
        ]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers?search=Empresa+Única');
        $response->assertOk();
    }

    public function test_equipment_search(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/equipments?search=balança');
        $response->assertOk();
    }

    public function test_wo_search(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders?search=calibração');
        $response->assertOk();
    }

    // ═══ Filters ═══

    public function test_wo_filter_by_status(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders?status=pending');
        $response->assertOk();
    }

    public function test_wo_filter_by_date(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders?from=2026-01-01&to=2026-12-31');
        $response->assertOk();
    }

    public function test_customer_filter_by_type(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers?type=company');
        $response->assertOk();
    }

    // ═══ Cross-Tenant Security ═══

    public function test_customer_show_cross_tenant_blocked(): void
    {
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $this->other->id]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/customers/{$foreignCustomer->id}");
        $response->assertNotFound();
    }

    public function test_wo_show_cross_tenant_blocked(): void
    {
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $this->other->id]);
        $foreignWO = WorkOrder::factory()->create([
            'tenant_id' => $this->other->id,
            'customer_id' => $foreignCustomer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/work-orders/{$foreignWO->id}");
        $response->assertNotFound();
    }

    public function test_equipment_show_cross_tenant_blocked(): void
    {
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $this->other->id]);
        $foreignEq = Equipment::factory()->create([
            'tenant_id' => $this->other->id,
            'customer_id' => $foreignCustomer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/equipments/{$foreignEq->id}");
        $response->assertNotFound();
    }

    public function test_supplier_show_cross_tenant_blocked(): void
    {
        $foreignSupplier = Supplier::factory()->create(['tenant_id' => $this->other->id]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/suppliers/{$foreignSupplier->id}");
        $response->assertNotFound();
    }

    public function test_customer_update_cross_tenant_blocked(): void
    {
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $this->other->id]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/customers/{$foreignCustomer->id}", [
            'name' => 'Hacked',
        ]);
        $response->assertNotFound();
    }

    public function test_customer_delete_cross_tenant_blocked(): void
    {
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $this->other->id]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/customers/{$foreignCustomer->id}");
        $response->assertNotFound();
    }

    // ═══ Sorting ═══

    public function test_customers_sort_by_name(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers?sort=name&direction=asc');
        $response->assertOk();
    }

    public function test_wo_sort_by_created_at(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders?sort=created_at&direction=desc');
        $response->assertOk();
    }
}
