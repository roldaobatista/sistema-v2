<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Batch final de testes Feature: Export, Batch operations,
 * Advanced filters, Statistics endpoints, Missing endpoints.
 */
class FinalBatchApiTest extends TestCase
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

    // ═══ Export endpoints ═══

    public function test_customers_export(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers/export');
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 404]
    }

    public function test_work_orders_export(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders/export');
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 404]
    }

    // ═══ Customer advanced ═══

    public function test_customer_update(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/api/v1/customers/{$this->customer->id}", [
            'name' => 'Nome Atualizado',
        ]);
        $response->assertOk();
    }

    public function test_customer_delete(): void
    {
        $c = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/customers/{$c->id}");
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 204]
    }

    public function test_customer_contacts(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/v1/customers/{$this->customer->id}/contacts");
        $response->assertOk();
    }

    public function test_customer_equipments(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/v1/customers/{$this->customer->id}/equipments");
        $response->assertOk();
    }

    public function test_customer_work_orders(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/v1/customers/{$this->customer->id}/work-orders");
        $response->assertOk();
    }

    // ═══ Supplier advanced ═══

    public function test_supplier_update(): void
    {
        $s = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/suppliers/{$s->id}", [
            'name' => 'Atualizado',
        ]);
        $response->assertOk();
    }

    public function test_supplier_delete(): void
    {
        $s = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/suppliers/{$s->id}");
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 204]
    }

    // ═══ AR/AP advanced ═══

    public function test_ar_index(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/accounts-receivable')->assertOk();
    }

    public function test_ap_index(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/accounts-payable')->assertOk();
    }

    public function test_ar_show(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->actingAs($this->admin)->getJson("/api/v1/accounts-receivable/{$ar->id}")->assertOk();
    }

    public function test_ap_show(): void
    {
        $ap = AccountPayable::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->admin)->getJson("/api/v1/accounts-payable/{$ap->id}")->assertOk();
    }

    // ═══ Quote advanced ═══

    public function test_quote_show(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->actingAs($this->admin)->getJson("/api/v1/quotes/{$q->id}")->assertOk();
    }

    public function test_quote_update(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/quotes/{$q->id}", [
            'notes' => 'Observação atualizada',
        ]);
        $response->assertOk();
    }

    // ═══ Equipment advanced ═══

    public function test_equipment_update(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/equipments/{$eq->id}", [
            'description' => 'Balança Atualizada',
        ]);
        $response->assertOk();
    }

    public function test_equipment_delete(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/equipments/{$eq->id}");
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 204]
    }

    // ═══ WO advanced ═══

    public function test_wo_show(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->actingAs($this->admin)->getJson("/api/v1/work-orders/{$wo->id}")->assertOk();
    }

    public function test_wo_delete(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/work-orders/{$wo->id}");
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 204]
    }

    // ═══ Invoice advanced ═══

    public function test_invoice_update(): void
    {
        $inv = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/invoices/{$inv->id}", [
            'observations' => 'Updated',
        ]);
        $response->assertOk();
    }

    // ═══ Settings ═══

    public function test_settings_update(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/v1/settings', [
            'company_name' => 'Updated Company',
        ]);
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 204]
    }

    // ═══ Roles ═══

    public function test_roles_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/roles', [
            'name' => 'custom_role_test',
        ]);
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 201]
    }

    // ═══ Expense advanced ═══

    public function test_expense_show(): void
    {
        $ex = Expense::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->admin)->getJson("/api/v1/expenses/{$ex->id}")->assertOk();
    }

    public function test_expense_approve(): void
    {
        $ex = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
        $response = $this->actingAs($this->admin)->postJson("/api/v1/expenses/{$ex->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 204]
    }
}
