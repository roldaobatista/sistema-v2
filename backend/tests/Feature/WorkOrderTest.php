<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderItem;
use App\Services\StockService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(fn () => true);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'type' => Warehouse::TYPE_FIXED,
            'name' => 'Central Warehouse',
            'code' => 'CENTRAL',
            'is_active' => true,
        ]);
    }

    // ── CRUD ──

    public function test_create_work_order(): void
    {
        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => 'Calibração de balança rodoviária',
            'priority' => 'high',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('work_orders', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }

    public function test_create_work_order_accepts_schedule_and_service_ops_fields(): void
    {
        $assignee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => 'OS agendada pelo app',
            'assignee_id' => $assignee->id,
            'scheduled_date' => '2030-03-26 14:30:00',
            'delivery_forecast' => '2030-03-28',
            'tags' => ['campo', 'mobile'],
            'photo_checklist' => [
                'items' => [
                    ['id' => 'front', 'text' => 'Foto frontal', 'checked' => true],
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.assigned_to', $assignee->id)
            ->assertJsonPath('data.scheduled_date', '2030-03-26T14:30:00+00:00')
            ->assertJsonPath('data.delivery_forecast', '2030-03-28')
            ->assertJsonPath('data.tags.1', 'mobile')
            ->assertJsonPath('data.photo_checklist.items.0.id', 'front');

        $workOrder = WorkOrder::query()->latest('id')->firstOrFail();

        $this->assertSame($assignee->id, $workOrder->assigned_to);
        $this->assertSame('2030-03-26 14:30:00', $workOrder->scheduled_date?->format('Y-m-d H:i:s'));
        $this->assertSame('2030-03-28', $workOrder->delivery_forecast?->toDateString());
        $this->assertSame(['campo', 'mobile'], $workOrder->tags);
        $this->assertSame('front', $workOrder->photo_checklist['items'][0]['id'] ?? null);
    }

    public function test_create_work_order_accepts_retroactive_in_service_status(): void
    {
        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => 'OS retroativa em atendimento',
            'initial_status' => WorkOrder::STATUS_IN_SERVICE,
            'received_at' => '2026-03-10 08:00:00',
            'started_at' => '2026-03-10 09:00:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', WorkOrder::STATUS_IN_SERVICE)
            ->assertJsonPath('data.started_at', '2026-03-10T09:00:00+00:00');

        $workOrder = WorkOrder::query()->latest('id')->firstOrFail();

        $this->assertSame(WorkOrder::STATUS_IN_SERVICE, $workOrder->status);
        $this->assertSame('2026-03-10 09:00:00', $workOrder->started_at?->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('work_order_status_history', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'to_status' => WorkOrder::STATUS_IN_SERVICE,
        ]);
    }

    public function test_create_work_order_rejects_quote_from_different_customer(): void
    {
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'quote_id' => $quote->id,
            'description' => 'OS com origem inconsistente',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quote_id']);
    }

    public function test_create_work_order_rejects_service_call_from_different_customer(): void
    {
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $serviceCall = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'service_call_id' => $serviceCall->id,
            'description' => 'OS com chamado inconsistente',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_call_id']);
    }

    public function test_create_work_order_rejects_equipment_from_different_customer(): void
    {
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'equipment_id' => $equipment->id,
            'equipment_ids' => [$equipment->id],
            'description' => 'OS com equipamento inconsistente',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['equipment_id', 'equipment_ids']);
    }

    public function test_list_work_orders(): void
    {
        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/work-orders');

        $response->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('meta.status_counts.open', 3)
            ->assertJsonPath('status_counts.open', 3);
    }

    public function test_show_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$wo->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $wo->id);
    }

    public function test_update_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}", [
            'priority' => 'urgent',
            'description' => 'Descrição atualizada',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'priority' => 'urgent',
        ]);
    }

    public function test_update_work_order_accepts_assignee_alias_and_service_ops_fields(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $assignee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $payload = [
            'assignee_id' => $assignee->id,
            'delivery_forecast' => '2026-03-25',
            'tags' => ['campo', 'urgente'],
            'photo_checklist' => [
                'items' => [
                    ['id' => '1', 'text' => 'Foto painel', 'checked' => true],
                ],
                'before' => [
                    ['path' => 'work-orders/1/checklist/before/photo.jpg'],
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.assigned_to', $assignee->id)
            ->assertJsonPath('data.delivery_forecast', '2026-03-25')
            ->assertJsonPath('data.tags.0', 'campo')
            ->assertJsonPath('data.photo_checklist.items.0.text', 'Foto painel');

        $wo->refresh();

        $this->assertSame($assignee->id, $wo->assigned_to);
        $this->assertSame('2026-03-25', $wo->delivery_forecast?->toDateString());
        $this->assertSame(['campo', 'urgente'], $wo->tags);
        $this->assertSame('Foto painel', $wo->photo_checklist['items'][0]['text'] ?? null);
        $this->assertSame('work-orders/1/checklist/before/photo.jpg', $wo->photo_checklist['before'][0]['path'] ?? null);
    }

    public function test_update_work_order_rejects_equipment_from_different_customer(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}", [
            'equipment_id' => $equipment->id,
            'equipment_ids' => [$equipment->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['equipment_id', 'equipment_ids']);
    }

    public function test_show_work_order_exposes_profitability_with_cost_price(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'displacement_value' => 10,
        ]);

        WorkOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'type' => 'product',
            'description' => 'Peça crítica',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 40,
            'discount' => 0,
        ]);

        $wo->refresh();

        $response = $this->getJson("/api/v1/work-orders/{$wo->id}");

        $response->assertOk()
            ->assertJsonPath('data.estimated_profit.revenue', '110.00')
            ->assertJsonPath('data.estimated_profit.costs', '55.50')
            ->assertJsonPath('data.estimated_profit.profit', '54.50')
            ->assertJsonPath('data.estimated_profit.breakdown.items_cost', '40.00');

        $costEstimate = $this->getJson("/api/v1/work-orders/{$wo->id}/cost-estimate");

        $costEstimate->assertOk()
            ->assertJsonPath('data.revenue', '110.00')
            ->assertJsonPath('data.total_cost', '55.50')
            ->assertJsonPath('data.profit', '54.50');
    }

    public function test_show_work_order_and_cost_estimate_share_unified_financial_totals(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'discount_percentage' => '10.00',
            'discount' => '0.00',
            'displacement_value' => '15.00',
        ]);

        WorkOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'type' => 'service',
            'description' => 'Serviço principal',
            'quantity' => 2,
            'unit_price' => 100,
            'discount' => 30,
        ]);

        WorkOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'type' => 'product',
            'description' => 'Peça complementar',
            'quantity' => 1,
            'unit_price' => 50,
            'discount' => 0,
        ]);

        $wo->refresh();
        $wo->recalculateTotal();
        $wo->refresh();

        $this->getJson("/api/v1/work-orders/{$wo->id}")
            ->assertOk()
            ->assertJsonPath('data.total', '213.00')
            ->assertJsonPath('data.discount_amount', '22.00');

        $estimateResponse = $this->getJson("/api/v1/work-orders/{$wo->id}/cost-estimate");

        $estimateResponse
            ->assertOk()
            ->assertJsonPath('data.items_subtotal', '250.00')
            ->assertJsonPath('data.items_discount', '30.00')
            ->assertJsonPath('data.displacement_value', '15.00')
            ->assertJsonPath('data.global_discount', '22.00')
            ->assertJsonPath('data.grand_total', '213.00');

        $items = collect($estimateResponse->json('data.items'))->keyBy('description');

        $this->assertSame('200.00', $items['Serviço principal']['line_subtotal']);
        $this->assertSame('170.00', $items['Serviço principal']['line_total']);
        $this->assertSame('50.00', $items['Peça complementar']['line_total']);
    }

    public function test_download_pdf_uses_unified_totals_from_domain_calculation(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'discount_percentage' => '10.00',
            'discount' => '0.00',
            'displacement_value' => '15.00',
        ]);

        WorkOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'type' => 'service',
            'description' => 'Serviço principal',
            'quantity' => 2,
            'unit_price' => 100,
            'discount' => 30,
        ]);

        WorkOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'type' => 'product',
            'description' => 'Peça complementar',
            'quantity' => 1,
            'unit_price' => 50,
            'discount' => 0,
        ]);

        $wo->refresh();
        $wo->recalculateTotal();
        $wo->refresh();

        $capturedHtml = null;
        $pdfMock = \Mockery::mock(PDF::class);
        $pdfMock->shouldReceive('setPaper')
            ->once()
            ->with('a4', 'portrait')
            ->andReturnSelf();
        $pdfMock->shouldReceive('download')
            ->once()
            ->with("os-{$wo->id}.pdf")
            ->andReturn(new Response('fake-pdf', 200, ['Content-Type' => 'application/pdf']));

        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadHTML')
            ->once()
            ->andReturnUsing(function ($html) use (&$capturedHtml, $pdfMock) {
                $capturedHtml = $html;

                return $pdfMock;
            });

        $this->get("/api/v1/work-orders/{$wo->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertNotNull($capturedHtml);
        $this->assertStringContainsString('Subtotal Bruto', $capturedHtml);
        $this->assertStringContainsString('R$ 250,00', $capturedHtml);
        $this->assertStringContainsString('Desconto dos Itens', $capturedHtml);
        $this->assertStringContainsString('- R$ 30,00', $capturedHtml);
        $this->assertStringContainsString('Desconto Global', $capturedHtml);
        $this->assertStringContainsString('- R$ 22,00', $capturedHtml);
        $this->assertStringContainsString('Deslocamento', $capturedHtml);
        $this->assertStringContainsString('R$ 15,00', $capturedHtml);
        $this->assertStringContainsString('TOTAL', $capturedHtml);
        $this->assertStringContainsString('R$ 213,00', $capturedHtml);
    }

    public function test_show_work_order_exposes_checkin_and_checkout_fields(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'checkin_at' => '2026-03-11 10:00:00',
            'checkin_lat' => -15.601,
            'checkin_lng' => -56.097,
            'checkout_at' => '2026-03-11 12:30:00',
            'checkout_lat' => -15.602,
            'checkout_lng' => -56.098,
            'auto_km_calculated' => 12.45,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$wo->id}");

        $response->assertOk()
            ->assertJsonPath('data.checkin_at', '2026-03-11T10:00:00+00:00')
            ->assertJsonPath('data.checkout_at', '2026-03-11T12:30:00+00:00')
            ->assertJsonPath('data.checkin_lat', -15.601)
            ->assertJsonPath('data.checkout_lng', -56.098)
            ->assertJsonPath('data.auto_km_calculated', '12.45');
    }

    public function test_delete_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/work-orders/{$wo->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('work_orders', ['id' => $wo->id]);
    }

    public function test_delete_work_order_with_financial_links_returns_conflict(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $wo->id,
            'created_by' => $this->user->id,
            'description' => 'Título vinculado',
            'amount' => 100,
            'due_date' => now()->addDays(30),
            'status' => 'pending',
        ]);

        $response = $this->deleteJson("/api/v1/work-orders/{$wo->id}");

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Não é possível excluir esta OS — possui títulos financeiros vinculados']);

        $this->assertDatabaseHas('work_orders', ['id' => $wo->id]);
    }

    // ── Status Transitions ──

    public function test_transition_open_to_awaiting_dispatch(): void
    {
        Event::fake();

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_AWAITING_DISPATCH,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_AWAITING_DISPATCH,
        ]);
    }

    public function test_invalid_status_transition_blocked(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        $response->assertStatus(422);
    }

    public function test_uninvoice_reverses_only_invoiced_commissions_and_preserves_completed_commissions(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        Invoice::factory()->issued()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'work_order_id' => $wo->id,
        ]);

        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $wo->id,
            'created_by' => $this->user->id,
            'description' => 'Titulo da OS',
            'amount' => 1000,
            'amount_paid' => 0,
            'due_date' => now()->addDays(30),
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $completedRule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Comissao conclusao',
            'type' => 'percentage',
            'value' => 5,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $invoicedRule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Comissao faturamento',
            'type' => 'percentage',
            'value' => 7,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_INVOICED,
        ]);

        $completedCommission = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $completedRule->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 50,
            'proportion' => 1,
            'status' => CommissionEvent::STATUS_PENDING,
            'notes' => 'Regra: Comissao conclusao (percent_gross) | trigger:os_completed',
        ]);

        $invoicedCommission = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $invoicedRule->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 70,
            'proportion' => 1,
            'status' => CommissionEvent::STATUS_APPROVED,
            'notes' => 'Regra: Comissao faturamento (percent_gross) | trigger:os_invoiced',
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/uninvoice");

        $response->assertOk();

        $completedCommission->refresh();
        $invoicedCommission->refresh();
        $wo->refresh();

        $this->assertSame(WorkOrder::STATUS_DELIVERED, $wo->status);
        $this->assertEquals(CommissionEvent::STATUS_PENDING, $completedCommission->status->value ?? $completedCommission->status);
        $this->assertEquals(CommissionEvent::STATUS_REVERSED, $invoicedCommission->status->value ?? $invoicedCommission->status);
        $this->assertDatabaseHas('work_order_events', [
            'work_order_id' => $wo->id,
            'event_type' => WorkOrderEvent::TYPE_STATUS_CHANGED,
        ]);
    }

    public function test_uninvoice_blocks_when_invoiced_commission_is_paid(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $wo->id,
            'created_by' => $this->user->id,
            'description' => 'Titulo da OS',
            'amount' => 1000,
            'amount_paid' => 0,
            'due_date' => now()->addDays(30),
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $invoicedRule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Comissao faturamento paga',
            'type' => 'percentage',
            'value' => 7,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_INVOICED,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $invoicedRule->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 70,
            'proportion' => 1,
            'status' => CommissionEvent::STATUS_PAID,
            'notes' => 'Regra: Comissao faturamento paga (percent_gross) | trigger:os_invoiced',
        ]);

        $this->postJson("/api/v1/work-orders/{$wo->id}/uninvoice")
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Não é possível desfaturar — existem comissões de faturamento já pagas. Estorne as comissões primeiro.',
            ]);

        $wo->refresh();
        $this->assertSame(WorkOrder::STATUS_INVOICED, $wo->status);
    }

    public function test_completed_transition(): void
    {
        Event::fake();

        $wo = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
    }

    public function test_invoicing_work_order_updates_linked_quote_status(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_IN_EXECUTION,
        ]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'quote_id' => $quote->id,
            'status' => WorkOrder::STATUS_DELIVERED,
            'total' => 5000.00,
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 1,
            'unit_price' => 5000.00,
        ]);

        $this->postJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_INVOICED,
            'agreed_payment_method' => 'pix',
        ])->assertOk();

        $quote->refresh();

        $this->assertSame(Quote::STATUS_INVOICED, $quote->status->value ?? $quote->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Quote::class,
            'auditable_id' => $quote->id,
            'action' => 'status_changed',
        ]);
    }

    // ── Items ──

    public function test_add_item_to_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/items", [
            'type' => 'service',
            'description' => 'Calibração industrial',
            'quantity' => 1,
            'unit_price' => 450.00,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => $wo->id,
            'description' => 'Calibração industrial',
        ]);
    }

    // ── Metadata ──

    public function test_update_item_rejects_item_from_other_work_order(): void
    {
        $workOrderA = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $itemB = WorkOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrderB->id,
            'type' => 'service',
            'description' => 'Item da B',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
        ]);

        $response = $this->putJson("/api/v1/work-orders/{$workOrderA->id}/items/{$itemB->id}", [
            'description' => 'Tentativa indevida',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Item não pertence a esta OS');
    }

    public function test_delete_item_rejects_item_from_other_work_order(): void
    {
        $workOrderA = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $itemB = WorkOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrderB->id,
            'type' => 'service',
            'description' => 'Item da B',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
        ]);

        $response = $this->deleteJson("/api/v1/work-orders/{$workOrderA->id}/items/{$itemB->id}");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Item não pertence a esta OS');

        $this->assertDatabaseHas('work_order_items', ['id' => $itemB->id]);
    }

    public function test_add_item_rejects_product_reference_from_other_tenant(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $foreignProduct = Product::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/items", [
            'type' => 'product',
            'reference_id' => $foreignProduct->id,
            'description' => 'Item inválido',
            'quantity' => 1,
            'unit_price' => 150,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reference_id']);
    }

    public function test_metadata_returns_statuses_and_priorities(): void
    {
        $response = $this->getJson('/api/v1/work-orders-metadata');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['statuses', 'priorities']]);
    }

    public function test_work_orders_metadata_route_is_not_captured_by_show_route_binding(): void
    {
        $this->getJson('/api/v1/work-orders-metadata')
            ->assertOk()
            ->assertJsonStructure(['data' => ['statuses', 'priorities']]);
    }

    // ── Tenant Isolation ──

    public function test_work_orders_isolated_by_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'OS de Outro Tenant',
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'OS do Meu Tenant',
        ]);

        $response = $this->getJson('/api/v1/work-orders');

        $response->assertOk()
            ->assertDontSee('OS de Outro Tenant');
    }

    // ── Filter & Search ──

    public function test_filter_by_status(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        WorkOrder::factory()->inProgress()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/work-orders?status=in_progress');

        $response->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_list_work_orders_filters_by_schedule_window(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'scheduled_date' => '2026-03-20 08:00:00',
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'scheduled_date' => '2026-04-05 09:00:00',
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'scheduled_date' => null,
        ]);

        $response = $this->getJson('/api/v1/work-orders?has_schedule=1&scheduled_from=2026-03-01&scheduled_to=2026-03-31');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.scheduled_date', '2026-03-20T08:00:00+00:00');
    }

    // ── Business Rules ──

    public function test_cannot_delete_completed_work_order(): void
    {
        $wo = WorkOrder::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/work-orders/{$wo->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível excluir OS concluída, entregue ou faturada');

        $this->assertDatabaseHas('work_orders', ['id' => $wo->id]);
    }

    public function test_rejects_invalid_status_transition(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        // open → delivered is not allowed
        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'allowed']);

        $this->assertDatabaseHas('work_orders', ['id' => $wo->id, 'status' => WorkOrder::STATUS_OPEN]);
    }

    // ── New Endpoints ──

    public function test_duplicate_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        WorkOrderItem::create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'service',
            'description' => 'Test Service',
            'quantity' => 2,
            'unit_price' => '100.00',
            'discount' => '0.00',
            'total' => '200.00',
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', WorkOrder::STATUS_OPEN);

        $newId = $response->json('data.id');
        $this->assertNotEquals($wo->id, $newId);
        $this->assertDatabaseCount('work_orders', 2);
    }

    public function test_duplicate_work_order_clears_operational_and_payment_fields(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_DELIVERED,
            'received_at' => now()->subDays(3),
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
            'delivered_at' => now(),
            'agreed_payment_method' => 'pix',
            'agreed_payment_notes' => 'Entrada + saldo',
            'service_started_at' => now()->subDay(),
            'wait_time_minutes' => 10,
            'service_duration_minutes' => 55,
            'total_duration_minutes' => 80,
            'arrival_latitude' => -15.1,
            'arrival_longitude' => -56.1,
            'checkin_at' => now()->subDay(),
            'checkin_lat' => -15.2,
            'checkin_lng' => -56.2,
            'checkout_at' => now()->subHours(12),
            'checkout_lat' => -15.3,
            'checkout_lng' => -56.3,
            'auto_km_calculated' => 23.4,
            'return_started_at' => now()->subHours(10),
            'return_arrived_at' => now()->subHours(9),
            'return_duration_minutes' => 33,
            'return_destination' => 'Base central',
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', WorkOrder::STATUS_OPEN)
            ->assertJsonPath('data.agreed_payment_method', null)
            ->assertJsonPath('data.started_at', null)
            ->assertJsonPath('data.completed_at', null)
            ->assertJsonPath('data.delivered_at', null)
            ->assertJsonPath('data.checkin_at', null)
            ->assertJsonPath('data.checkout_at', null)
            ->assertJsonPath('data.return_started_at', null)
            ->assertJsonPath('data.return_arrived_at', null);

        $newId = $response->json('data.id');
        $newOrder = WorkOrder::findOrFail($newId);

        $this->assertNull($newOrder->received_at);
        $this->assertNull($newOrder->agreed_payment_method);
        $this->assertNull($newOrder->agreed_payment_notes);
        $this->assertNull($newOrder->service_started_at);
        $this->assertNull($newOrder->checkin_at);
        $this->assertNull($newOrder->checkout_at);
        $this->assertNull($newOrder->return_started_at);
        $this->assertNull($newOrder->return_arrived_at);
        $this->assertNull($newOrder->return_destination);
        $this->assertNull($newOrder->auto_km_calculated);
        $this->assertNull($newOrder->arrival_latitude);
        $this->assertNull($newOrder->arrival_longitude);
        $this->assertNull($newOrder->wait_time_minutes);
        $this->assertNull($newOrder->service_duration_minutes);
        $this->assertNull($newOrder->total_duration_minutes);
        $this->assertNull($newOrder->return_duration_minutes);
    }

    public function test_reopen_cancelled_work_order(): void
    {
        $wo = WorkOrder::factory()->cancelled()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cliente cancelou',
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/reopen");

        $response->assertOk();

        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $wo->refresh();
        $this->assertNull($wo->cancelled_at);
        $this->assertNull($wo->cancellation_reason);
        $this->assertDatabaseHas('work_order_events', [
            'work_order_id' => $wo->id,
            'event_type' => WorkOrderEvent::TYPE_STATUS_CHANGED,
        ]);
    }

    public function test_reopen_cancelled_work_order_recreates_stock_reservation(): void
    {
        $warehouseId = Warehouse::where('tenant_id', $this->tenant->id)->value('id');
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
            'stock_qty' => 10,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $wo->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => WorkOrderItem::TYPE_PRODUCT,
            'reference_id' => $product->id,
            'description' => $product->name,
            'quantity' => 2,
            'unit_price' => 100,
            'warehouse_id' => $warehouseId,
        ]);

        $this->assertSame('8.00', $product->fresh()->stock_qty);

        $wo->updateQuietly([
            'status' => WorkOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelamento manual',
        ]);
        app(StockService::class)->returnStock($product, 2, $wo, $warehouseId);

        $this->assertSame('10.00', $product->fresh()->stock_qty);

        $this->postJson("/api/v1/work-orders/{$wo->id}/reopen")
            ->assertOk();

        $this->assertSame('8.00', $product->fresh()->stock_qty);
    }

    public function test_reopen_cancelled_work_order_is_blocked_when_stock_is_unavailable(): void
    {
        $warehouseId = Warehouse::where('tenant_id', $this->tenant->id)->value('id');
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'track_stock' => true,
            'stock_qty' => 10,
        ]);

        $stock = WarehouseStock::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $wo->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => WorkOrderItem::TYPE_PRODUCT,
            'reference_id' => $product->id,
            'description' => $product->name,
            'quantity' => 4,
            'unit_price' => 100,
            'warehouse_id' => $warehouseId,
        ]);

        $wo->updateQuietly([
            'status' => WorkOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelamento manual',
        ]);
        app(StockService::class)->returnStock($product, 4, $wo, $warehouseId);

        $stock->update(['quantity' => 2]);
        $product->update(['stock_qty' => 2]);

        $this->postJson("/api/v1/work-orders/{$wo->id}/reopen")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nao foi possivel reabrir a OS porque o estoque reservado nao esta mais disponivel.');

        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_CANCELLED,
        ]);
    }

    public function test_reopen_non_cancelled_returns_422(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/reopen");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status' => 'Apenas OS canceladas podem ser reabertas.']);
    }

    public function test_export_csv(): void
    {
        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get('/api/v1/work-orders-export');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_dashboard_stats(): void
    {
        WorkOrder::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/work-orders-dashboard-stats');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'status_counts',
                'avg_completion_hours',
                'month_revenue',
                'sla_compliance',
                'total_orders',
                'top_customers',
            ]]);
    }
}
