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
 * Testes profundos para Supplier API, Employee API, Inventory API, e Agenda API.
 */
class ExtendedModulesApiTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    // ═══ Supplier API ═══

    public function test_suppliers_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/suppliers');
        $response->assertOk();
    }

    public function test_supplier_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/suppliers', [
            'name' => 'Fornecedor XYZ',
            'document' => '11222333000181',
            'type' => 'company',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    public function test_supplier_show(): void
    {
        $s = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/suppliers/{$s->id}");
        $response->assertOk();
    }

    public function test_supplier_update(): void
    {
        $s = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/suppliers/{$s->id}", [
            'name' => 'Fornecedor Atualizado',
        ]);
        $response->assertOk();
    }

    public function test_supplier_destroy(): void
    {
        $s = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/suppliers/{$s->id}");
        $this->assertTrue(in_array($response->status(), [200, 204]));
    }

    // ═══ Inventory API ═══

    public function test_inventory_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/inventory');
        $response->assertOk();
    }

    public function test_inventory_categories(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/inventory/categories');
        $response->assertOk();
    }

    // ═══ Agenda API ═══

    public function test_agenda_items_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/agenda-items');
        $response->assertOk();
    }

    public function test_agenda_items_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/agenda-items', [
            'titulo' => 'Tarefa de Teste',
            'tipo' => 'tarefa',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    // ═══ Service Calls API ═══

    public function test_service_calls_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/service-calls');
        $response->assertOk();
    }

    public function test_service_call_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/service-calls', [
            'customer_id' => $this->customer->id,
            'description' => 'Chamado de teste de calibração',
            'priority' => 'medium',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    // ═══ Contracts API ═══

    public function test_contracts_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/contracts');
        $response->assertOk();
    }

    // ═══ Import API ═══

    public function test_import_fields_customers(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/import/fields/customers');
        $response->assertOk();
    }

    public function test_import_fields_equipments(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/import/fields/equipments');
        $response->assertOk();
    }

    public function test_import_fields_suppliers(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/import/fields/suppliers');
        $response->assertOk();
    }

    // ═══ Calibration API ═══

    public function test_calibration_certificates_index(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/equipments/{$eq->id}/calibrations");
        $response->assertOk();
    }

    // ═══ Analytics/Dashboard ═══

    public function test_analytics_overview(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/analytics/overview');
        $response->assertOk();
    }

    // ═══ Audit Log API ═══

    public function test_audit_logs_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs');
        $response->assertOk();
    }

    // ═══ HR API ═══

    public function test_hr_employees_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/hr/employees');
        $response->assertOk();
    }

    // ═══ Cash Flow ═══

    public function test_cash_flow_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/cash-flow');
        $response->assertOk();
    }

    // ═══ Work Order Advanced ═══

    public function test_wo_duplicate(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->postJson("/api/v1/work-orders/{$wo->id}/duplicate");
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    // ═══ Unauthenticated endpoints ═══

    public function test_suppliers_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/suppliers');
        $response->assertUnauthorized();
    }

    public function test_inventory_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/inventory');
        $response->assertUnauthorized();
    }

    public function test_agenda_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/agenda-items');
        $response->assertUnauthorized();
    }

    public function test_service_calls_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/service-calls');
        $response->assertUnauthorized();
    }
}
