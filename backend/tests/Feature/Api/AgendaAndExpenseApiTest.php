<?php

namespace Tests\Feature\Api;

use App\Enums\AgendaItemStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\AgendaItem;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes profundos para Agenda API, Expense API, Work Order Items API,
 * Notification API, Analytics avançado.
 */
class AgendaAndExpenseApiTest extends TestCase
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

    // ═══ Agenda API avançado ═══

    public function test_agenda_show(): void
    {
        $item = AgendaItem::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/agenda-items/{$item->id}");
        $response->assertOk();
    }

    public function test_agenda_update(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => AgendaItemStatus::ABERTO,
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/agenda-items/{$item->id}", [
            'title' => 'Atualizado',
        ]);
        $response->assertOk();
    }

    public function test_agenda_delete(): void
    {
        $item = AgendaItem::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/agenda-items/{$item->id}");
        $this->assertTrue(in_array($response->status(), [200, 204]));
    }

    public function test_agenda_filter_status(): void
    {
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => AgendaItemStatus::ABERTO,
        ]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/agenda-items?status=aberto');
        $response->assertOk();
    }

    public function test_agenda_filter_tipo(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/agenda-items?tipo=tarefa');
        $response->assertOk();
    }

    // ═══ Expense API ═══

    public function test_expenses_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/expenses');
        $response->assertOk();
    }

    public function test_expense_store(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->postJson('/api/v1/expenses', [
            'work_order_id' => $wo->id,
            'description' => 'Peça de reposição',
            'amount' => '350.00',
            'expense_date' => now()->format('Y-m-d'),
            'category' => 'pecas',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    public function test_expense_show(): void
    {
        $ex = Expense::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/expenses/{$ex->id}");
        $response->assertOk();
    }

    public function test_expense_update(): void
    {
        $ex = Expense::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/expenses/{$ex->id}", [
            'description' => 'Atualizado',
        ]);
        $response->assertOk();
    }

    // ═══ Work Order Items API ═══

    public function test_wo_items_index(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/work-orders/{$wo->id}/items");
        $response->assertOk();
    }

    public function test_wo_items_store(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->postJson("/api/v1/work-orders/{$wo->id}/items", [
            'description' => 'Serviço de calibração',
            'quantity' => 1,
            'unit_price' => '500.00',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    // ═══ Notification API ═══

    public function test_notifications_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/notifications');
        $response->assertOk();
    }

    public function test_notifications_mark_all_read(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/notifications/mark-all-read');
        $this->assertTrue(in_array($response->status(), [200, 204]));
    }

    public function test_notifications_unread_count(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/notifications/unread-count');
        $response->assertOk();
    }

    // ═══ DRE API ═══

    public function test_dre_api(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports/dre?from=2026-01-01&to=2026-03-31');
        $response->assertOk();
    }

    // ═══ Cash Flow API ═══

    public function test_cash_flow_api(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/cash-flow?from=2026-01-01&to=2026-01-31');
        $response->assertOk();
    }

    // ═══ Export API ═══

    public function test_export_customers(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/export/customers');
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }

    // ═══ Unauthenticated ═══

    public function test_expenses_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/expenses');
        $response->assertUnauthorized();
    }

    public function test_notifications_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/notifications');
        $response->assertUnauthorized();
    }

    public function test_dre_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/reports/dre');
        $response->assertUnauthorized();
    }
}
