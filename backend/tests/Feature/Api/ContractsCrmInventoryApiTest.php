<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Contract;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes finais de API: Contracts, CRM Pipeline, Deals,
 * Departments, Inventory, Notifications, Reports DRE/CashFlow.
 */
class ContractsCrmInventoryApiTest extends TestCase
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

    // ═══ Contracts API ═══

    public function test_contracts_index(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/contracts')->assertOk();
    }

    public function test_contract_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/contracts', [
            'customer_id' => $this->customer->id,
            'description' => 'Contrato Anual de Calibração',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'value' => '12000.00',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    public function test_contract_show(): void
    {
        $c = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->actingAs($this->admin)->getJson("/api/v1/contracts/{$c->id}")->assertOk();
    }

    public function test_contract_update(): void
    {
        $c = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->actingAs($this->admin)->putJson("/api/v1/contracts/{$c->id}", [
            'value' => '15000.00',
        ])->assertOk();
    }

    // ═══ CRM Pipeline API ═══

    public function test_pipelines_index(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/crm/pipelines')->assertOk();
    }

    public function test_pipeline_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/crm/pipelines', [
            'name' => 'Pipeline Vendas',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    // ═══ CRM Deals API ═══

    public function test_deals_index(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/crm/deals')->assertOk();
    }

    public function test_deal_store(): void
    {
        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
        ]);
        $response = $this->actingAs($this->admin)->postJson('/api/v1/crm/deals', [
            'title' => 'Negócio Teste',
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'value' => '50000.00',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    // ═══ Departments API ═══

    public function test_departments_index(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/departments')->assertOk();
    }

    // ═══ Inventory API ═══

    public function test_inventory_index(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/inventory-items')->assertOk();
    }

    public function test_inventory_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/inventory-items', [
            'name' => 'Massa Padrão 1kg',
            'sku' => 'MP-001',
            'current_stock' => 10,
            'minimum_stock' => 5,
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    // ═══ Notifications API ═══

    public function test_notifications_index(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/notifications')->assertOk();
    }

    public function test_notifications_mark_as_read(): void
    {
        $notif = Notification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'read_at' => null,
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/notifications/{$notif->id}/read");
        $this->assertTrue(in_array($response->status(), [200, 204]));
    }

    public function test_notifications_mark_all_read(): void
    {
        Notification::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'read_at' => null,
        ]);
        $response = $this->actingAs($this->admin)->postJson('/api/v1/notifications/mark-all-read');
        $this->assertTrue(in_array($response->status(), [200, 204]));
    }

    // ═══ Reports API ═══

    public function test_dre_report(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/reports/dre?from=2026-01-01&to=2026-12-31')->assertOk();
    }

    public function test_cash_flow_report(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/reports/cash-flow?from=2026-01-01&to=2026-12-31')->assertOk();
    }

    // ═══ Unauthenticated ═══

    public function test_contracts_unauthenticated(): void
    {
        $this->getJson('/api/v1/contracts')->assertUnauthorized();
    }

    public function test_notifications_unauthenticated(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }
}
