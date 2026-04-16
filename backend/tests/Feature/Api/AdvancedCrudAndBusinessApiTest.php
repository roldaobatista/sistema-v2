<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes profundos de APIs avançadas: Invoice, ServiceCall, BankAccount,
 * Numbering, WorkOrder status, Profile, Permissions.
 */
class AdvancedCrudAndBusinessApiTest extends TestCase
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

    // ═══ Invoice API ═══

    public function test_invoice_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/invoices');
        $response->assertOk();
    }

    public function test_invoice_store(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->postJson('/api/v1/invoices', [
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    public function test_invoice_show(): void
    {
        $inv = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/invoices/{$inv->id}");
        $response->assertOk();
    }

    // ═══ Service Call API ═══

    public function test_service_call_show(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/service-calls/{$sc->id}");
        $response->assertOk();
    }

    public function test_service_call_update(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/service-calls/{$sc->id}", [
            'description' => 'Atualizado',
        ]);
        $response->assertOk();
    }

    // ═══ Bank Account API ═══

    public function test_bank_accounts_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/bank-accounts');
        $response->assertOk();
    }

    public function test_bank_account_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/bank-accounts', [
            'bank_name' => 'Banco do Brasil',
            'account_type' => 'checking',
            'initial_balance' => '10000.00',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    // ═══ WO Status Flows ═══

    public function test_wo_change_status(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/work-orders/{$wo->id}", [
            'status' => 'in_progress',
        ]);
        $response->assertOk();
    }

    public function test_wo_complete(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'in_progress',
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/work-orders/{$wo->id}", [
            'status' => 'completed',
        ]);
        $response->assertOk();
    }

    // ═══ Profile API ═══

    public function test_profile_update(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/v1/profile', [
            'name' => 'Novo Nome',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 204]));
    }

    // ═══ Permissions API ═══

    public function test_permissions_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/permissions');
        $response->assertOk();
    }

    // ═══ Chart of Accounts API ═══

    public function test_chart_of_accounts_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/chart-of-accounts');
        $response->assertOk();
    }

    // ═══ Numbering Sequences API ═══

    public function test_numbering_sequences_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/numbering-sequences');
        $response->assertOk();
    }

    // ═══ Expense Categories API ═══

    public function test_expense_categories_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/expense-categories');
        $response->assertOk();
    }

    // ═══ Customer Stats ═══

    public function test_customer_stats(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/v1/customers/{$this->customer->id}/stats");
        $response->assertOk();
    }

    // ═══ Equipment History ═══

    public function test_equipment_work_order_history(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/equipments/{$eq->id}/work-orders");
        $response->assertOk();
    }

    // ═══ Unauthenticated ═══

    public function test_invoices_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/invoices');
        $response->assertUnauthorized();
    }

    public function test_bank_accounts_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/bank-accounts');
        $response->assertUnauthorized();
    }
}
