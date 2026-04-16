<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Professional Cross-Module Flow tests â€” replaces CrossModuleFlowTest.
 * Exact status assertions, DB verification at each step, proper flow validation.
 */
class CrossModuleFlowProfessionalTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document' => '33.000.167/0001-01',
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // â”€â”€ OS â†’ INVOICE â†’ RECEIVABLE (Full E2E) â”€â”€

    public function test_os_to_invoice_to_receivable_flow(): void
    {
        $woResponse = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => 'CalibraÃ§Ã£o industrial completa',
            'priority' => 'high',
            'total' => 1500.00,
        ]);
        $woResponse->assertCreated();
        $woId = $woResponse->json('data.id') ?? $woResponse->json('data.id');

        $this->assertDatabaseHas('work_orders', [
            'id' => $woId,
            'status' => WorkOrder::STATUS_OPEN,
            'customer_id' => $this->customer->id,
        ]);

        $this->putJson("/api/v1/work-orders/{$woId}/status", [
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ])->assertOk();

        $this->putJson("/api/v1/work-orders/{$woId}/status", [
            'status' => WorkOrder::STATUS_AT_CLIENT,
        ])->assertOk();

        $this->putJson("/api/v1/work-orders/{$woId}/status", [
            'status' => WorkOrder::STATUS_IN_SERVICE,
        ])->assertOk();

        $this->putJson("/api/v1/work-orders/{$woId}/status", [
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
        ])->assertOk();

        // Assign technician (required before completing)
        $wo = WorkOrder::find($woId);
        $wo->technicians()->attach($this->user->id);

        $this->putJson("/api/v1/work-orders/{$woId}/status", [
            'status' => WorkOrder::STATUS_COMPLETED,
        ])->assertOk();

        // Add item (required before invoicing)
        WorkOrderItem::factory()->create([
            'work_order_id' => $woId,
            'tenant_id' => $wo->tenant_id,
        ]);

        $this->putJson("/api/v1/work-orders/{$woId}/status", [
            'status' => WorkOrder::STATUS_DELIVERED,
            'agreed_payment_method' => 'pix',
        ])->assertOk();

        $this->putJson("/api/v1/work-orders/{$woId}/status", [
            'status' => WorkOrder::STATUS_INVOICED,
            'agreed_payment_method' => 'pix',
        ])->assertOk();

        $this->assertDatabaseHas('work_orders', [
            'id' => $woId,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);
        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $woId,
        ]);
        $this->assertDatabaseHas('accounts_receivable', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $woId,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_customer_chain_integrity_wo_linked_to_customer(): void
    {
        $custResponse = $this->postJson('/api/v1/customers', [
            'name' => 'Cliente Cadeia Completa',
            'type' => 'PJ',
            'document' => '06.990.590/0001-23',
        ]);
        $custResponse->assertCreated();
        $custId = $custResponse->json('data.id') ?? $custResponse->json('data.id');

        $woResponse = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $custId,
            'description' => 'OS vinculada ao cliente cadeia',
        ]);
        $woResponse->assertCreated();

        $woId = $woResponse->json('data.id') ?? $woResponse->json('data.id');

        $this->assertDatabaseHas('work_orders', [
            'id' => $woId,
            'customer_id' => $custId,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // â”€â”€ STATUS MACHINE ENFORCEMENT â”€â”€

    public function test_invalid_status_transition_returns_422(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }

    // â”€â”€ QUOTE â†’ OS â”€â”€

    public function test_quote_creation_persists_with_customer(): void
    {
        $eq = Equipment::factory()->create(['customer_id' => $this->customer->id, 'tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'title' => 'OrÃ§amento calibraÃ§Ã£o anual',
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
            'equipments' => [
                [
                    'equipment_id' => $eq->id,
                    'items' => [
                        [
                            'type' => 'service',
                            'service_id' => $service->id,
                            'quantity' => 1.0,
                            'original_price' => 150.00,
                            'unit_price' => 150.00,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertCreated();

        $quoteId = $response->json('data.id') ?? $response->json('data.id');

        $this->assertDatabaseHas('quotes', [
            'id' => $quoteId,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // â”€â”€ SERVICE CALL â†’ OS â”€â”€

    public function test_service_call_convert_creates_os(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'observations' => 'Chamado para converter',
            'status' => 'in_progress',
        ]);

        $response = $this->postJson("/api/v1/service-calls/{$sc->id}/convert-to-os");

        $response->assertStatus(201);

        $woId = $response->json('data.id') ?? $response->json('data.id');
        $this->assertNotNull($woId);

        $this->assertDatabaseHas('work_orders', [
            'id' => $woId,
            'customer_id' => $this->customer->id,
            'service_call_id' => $sc->id,
        ]);
    }

    // â”€â”€ DELETION CONSTRAINTS â”€â”€

    public function test_delete_customer_without_dependencies_succeeds(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Customer Sem OS',
        ]);

        $this->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertStatus(204);
    }

    public function test_delete_customer_with_work_orders_is_restricted(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/customers/{$this->customer->id}");

        $response->assertStatus(409);

        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);
    }
}
