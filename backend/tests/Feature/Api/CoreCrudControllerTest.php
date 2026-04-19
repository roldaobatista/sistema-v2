<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes profundos de CRUD completo dos controllers principais.
 * Testa cenários reais de index, store, show, update, destroy + filtros.
 */
class CoreCrudControllerTest extends TestCase
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

    // ═══ Customer CRUD ═══

    public function test_customer_index_returns_paginated(): void
    {
        Customer::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers');
        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_customer_store_with_valid_data(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/customers', [
            'name' => 'Supermercado Teste LTDA',
            'type' => 'PJ',
            'document' => '11222333000181',
            'email' => 'contato@supermercadoteste.com',
            'phone' => '11999887766',
            'address_zip' => '01310100',
            'address_street' => 'Av Paulista',
            'address_number' => '1000',
            'address_city' => 'São Paulo',
            'address_state' => 'SP',
        ]);
        $response->assertCreated();
    }

    public function test_customer_store_fails_without_name(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/customers', [
            'type' => 'PJ',
        ]);
        $response->assertUnprocessable();
    }

    public function test_customer_show(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/v1/customers/{$this->customer->id}");
        $response->assertOk();
        $response->assertJsonPath('data.id', $this->customer->id);
    }

    public function test_customer_update(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/api/v1/customers/{$this->customer->id}", [
            'name' => 'Nome Atualizado',
        ]);
        $response->assertOk();
        $this->assertEquals('Nome Atualizado', $this->customer->fresh()->name);
    }

    public function test_customer_destroy(): void
    {
        $c = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/customers/{$c->id}");
        $this->assertTrue(in_array($response->status(), [200, 204]));
        $this->assertSoftDeleted($c);
    }

    public function test_customer_search_by_name(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Padaria Central']);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers?search=Padaria');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_customer_filter_by_segment(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'segment' => 'supermercado']);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers?segment=supermercado');
        $response->assertOk();
    }

    // ═══ WorkOrder CRUD ═══

    public function test_work_order_index(): void
    {
        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders');
        $response->assertOk();
    }

    public function test_work_order_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => 'Calibração de balança rodoviária',
            'priority' => 'high',
            'service_type' => 'calibracao',
            'scheduled_date' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    public function test_work_order_store_fails_without_customer(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/work-orders', [
            'description' => 'Sem cliente',
        ]);
        $response->assertUnprocessable();
    }

    public function test_work_order_show(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/work-orders/{$wo->id}");
        $response->assertOk();
    }

    public function test_work_order_update_status(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/work-orders/{$wo->id}", [
            'status' => WorkOrder::STATUS_AWAITING_DISPATCH,
        ]);
        $response->assertOk();
    }

    public function test_work_order_filter_by_status(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders?status=completed');
        $response->assertOk();
    }

    public function test_work_order_filter_by_priority(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders?priority=urgent');
        $response->assertOk();
    }

    public function test_work_order_filter_by_customer(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/v1/work-orders?customer_id={$this->customer->id}");
        $response->assertOk();
    }

    // ═══ Equipment CRUD ═══

    public function test_equipment_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/equipments');
        $response->assertOk();
    }

    public function test_equipment_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'serial_number' => 'SN-'.rand(100000, 999999),
            'type' => 'Balança',
            'brand' => 'Toledo',
            'model' => 'Prix 3',
            'capacity' => '30.0000',
            'status' => 'active',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    public function test_equipment_show(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/equipments/{$eq->id}");
        $response->assertOk();
    }

    public function test_equipment_update(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/equipments/{$eq->id}", [
            'status' => Equipment::STATUS_IN_CALIBRATION,
        ]);
        $response->assertOk();
    }

    public function test_equipment_filter_overdue(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => now()->subDays(5),
        ]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/equipments?calibration_status=overdue');
        $response->assertOk();
    }

    // ═══ Pagination ═══

    public function test_customers_pagination_page_2(): void
    {
        Customer::factory()->count(25)->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers?page=2&per_page=10');
        $response->assertOk();
    }

    public function test_work_orders_per_page(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders?per_page=5');
        $response->assertOk();
    }

    // ═══ Cross-tenant isolation ═══

    public function test_customer_not_visible_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);
        $otherUser->assignRole('admin');

        app()->instance('current_tenant_id', $otherTenant->id);

        $response = $this->actingAs($otherUser)->getJson("/api/v1/customers/{$this->customer->id}");
        $response->assertNotFound();
    }

    public function test_work_order_not_visible_cross_tenant(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);
        $otherUser->assignRole('admin');

        app()->instance('current_tenant_id', $otherTenant->id);

        $response = $this->actingAs($otherUser)->getJson("/api/v1/work-orders/{$wo->id}");
        $response->assertNotFound();
    }
}
