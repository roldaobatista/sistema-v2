<?php

namespace Tests\Unit\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos que validam a lógica real do WorkOrder model:
 * canTransitionTo(), recalculateTotal(), businessNumber,
 * isTechnicianAuthorized(), warrantyDays(), estimatedProfit, constants.
 */
class WorkOrderRealLogicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);
    }

    // ── canTransitionTo() — real state machine logic (WorkOrderStatus enum) ──

    public function test_open_can_transition_to_awaiting_dispatch(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_AWAITING_DISPATCH));
    }

    public function test_open_can_transition_to_in_displacement(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_IN_DISPLACEMENT));
    }

    public function test_open_can_transition_to_cancelled(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_CANCELLED));
    }

    public function test_open_cannot_transition_to_completed(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $this->assertFalse($wo->canTransitionTo(WorkOrder::STATUS_COMPLETED));
    }

    public function test_open_cannot_transition_to_delivered(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $this->assertFalse($wo->canTransitionTo(WorkOrder::STATUS_DELIVERED));
    }

    public function test_in_displacement_can_transition_to_at_client(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_AT_CLIENT));
    }

    public function test_in_displacement_can_transition_to_displacement_paused(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_DISPLACEMENT_PAUSED));
    }

    public function test_displacement_paused_can_resume_displacement(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_DISPLACEMENT_PAUSED,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_IN_DISPLACEMENT));
    }

    public function test_at_client_can_transition_to_in_service(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_AT_CLIENT,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_IN_SERVICE));
    }

    public function test_in_service_can_transition_to_awaiting_return(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_IN_SERVICE,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_AWAITING_RETURN));
    }

    public function test_in_service_can_pause(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_IN_SERVICE,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_SERVICE_PAUSED));
    }

    public function test_in_service_can_wait_parts(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_IN_SERVICE,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_WAITING_PARTS));
    }

    public function test_service_paused_can_resume_service(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_SERVICE_PAUSED,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_IN_SERVICE));
    }

    public function test_awaiting_return_can_transition_to_in_return(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_IN_RETURN));
    }

    public function test_awaiting_return_can_transition_to_completed(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_COMPLETED));
    }

    public function test_completed_can_transition_to_delivered(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_DELIVERED));
    }

    public function test_delivered_can_transition_to_invoiced(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_DELIVERED,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_INVOICED));
    }

    public function test_invoiced_cannot_transition_anywhere(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);
        $this->assertFalse($wo->canTransitionTo(WorkOrder::STATUS_OPEN));
        $this->assertFalse($wo->canTransitionTo(WorkOrder::STATUS_COMPLETED));
        $this->assertFalse($wo->canTransitionTo(WorkOrder::STATUS_CANCELLED));
    }

    public function test_cancelled_can_reopen(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_CANCELLED,
        ]);
        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_OPEN));
    }

    public function test_cancelled_cannot_skip_to_completed(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_CANCELLED,
        ]);
        $this->assertFalse($wo->canTransitionTo(WorkOrder::STATUS_COMPLETED));
    }

    public function test_invalid_status_returns_false(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $this->assertFalse($wo->canTransitionTo('nonexistent_status'));
    }

    // ── recalculateTotal() ──

    public function test_recalculate_total_sums_items(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
            'discount_percentage' => '0.00',
            'discount' => '0.00',
            'displacement_value' => '0.00',
        ]);
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 5,
            'unit_price' => '100.00',
            'discount' => '0.00',
        ]);
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 3,
            'unit_price' => '100.00',
            'discount' => '0.00',
        ]);

        $wo->recalculateTotal();
        $wo->refresh();

        $this->assertEquals('800.00', $wo->total);
    }

    public function test_recalculate_total_with_percentage_discount(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
            'discount_percentage' => '10.00',
            'discount' => '0.00',
            'displacement_value' => '0.00',
        ]);
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 10,
            'unit_price' => '100.00',
            'discount' => '0.00',
        ]);

        $wo->recalculateTotal();
        $wo->refresh();

        $this->assertEquals('900.00', $wo->total);
    }

    public function test_recalculate_total_with_fixed_discount(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
            'discount_percentage' => '0.00',
            'discount' => '150.00',
            'displacement_value' => '0.00',
        ]);
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 10,
            'unit_price' => '100.00',
            'discount' => '0.00',
        ]);

        $wo->recalculateTotal();
        $wo->refresh();

        $this->assertEquals('850.00', $wo->total);
    }

    public function test_recalculate_total_with_displacement_value(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
            'discount_percentage' => '0.00',
            'discount' => '0.00',
            'displacement_value' => '100.00',
        ]);
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 5,
            'unit_price' => '100.00',
            'discount' => '0.00',
        ]);

        $wo->recalculateTotal();
        $wo->refresh();

        $this->assertEquals('600.00', $wo->total);
    }

    public function test_recalculate_total_never_negative(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
            'discount_percentage' => '0.00',
            'discount' => '999999.00',
            'displacement_value' => '0.00',
        ]);
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 1,
            'unit_price' => '100.00',
            'discount' => '0.00',
        ]);

        $wo->recalculateTotal();
        $wo->refresh();

        $this->assertEquals('0.00', $wo->total);
    }

    public function test_calculate_financial_totals_combines_item_discount_percentage_and_displacement(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'discount_percentage' => '10.00',
            'discount' => '0.00',
            'displacement_value' => '15.00',
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 2,
            'unit_price' => '100.00',
            'discount' => '30.00',
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 1,
            'unit_price' => '50.00',
            'discount' => '0.00',
        ]);

        $totals = $wo->calculateFinancialTotals();

        $this->assertSame('250.00', $totals['items_subtotal']);
        $this->assertSame('30.00', $totals['items_discount']);
        $this->assertSame('220.00', $totals['items_net_subtotal']);
        $this->assertSame('22.00', $totals['global_discount']);
        $this->assertSame('15.00', $totals['displacement_value']);
        $this->assertSame('213.00', $totals['grand_total']);
        $this->assertSame('170.00', $totals['items'][0]['line_total']);
        $this->assertSame('50.00', $totals['items'][1]['line_total']);
    }

    // ── businessNumber accessor ──

    public function test_business_number_returns_os_number_when_set(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'os_number' => 'OS-000042',
            'number' => 'OS-000042',
        ]);
        $this->assertEquals('OS-000042', $wo->business_number);
    }

    public function test_business_number_falls_back_to_number(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'os_number' => null,
            'number' => 'OS-000099',
        ]);
        $this->assertEquals('OS-000099', $wo->business_number);
    }

    // ── isTechnicianAuthorized() ──

    public function test_assigned_user_is_authorized(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->user->id,
        ]);
        $this->assertTrue($wo->isTechnicianAuthorized($this->user->id));
    }

    public function test_null_user_is_not_authorized(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertFalse($wo->isTechnicianAuthorized(null));
    }

    public function test_unrelated_user_is_not_authorized(): void
    {
        $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->user->id,
        ]);
        $this->assertFalse($wo->isTechnicianAuthorized($other->id));
    }

    public function test_technician_in_pivot_is_authorized(): void
    {
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $wo->technicians()->attach($tech->id, ['role' => 'technician']);

        $this->assertTrue($wo->isTechnicianAuthorized($tech->id));
    }

    // ── warrantyDays() ──

    public function test_warranty_days_default_90(): void
    {
        $days = WorkOrder::warrantyDays($this->tenant->id);
        $this->assertEquals(90, $days);
    }

    public function test_warranty_days_configurable(): void
    {
        SystemSetting::create([
            'tenant_id' => $this->tenant->id,
            'key' => 'warranty_days',
            'value' => '180',
        ]);
        $days = WorkOrder::warrantyDays($this->tenant->id);
        $this->assertEquals(180, $days);
    }

    public function test_warranty_until_with_completed_at(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'completed_at' => now()->subDays(10),
        ]);
        $warrantyDays = WorkOrder::warrantyDays($this->tenant->id);
        $expected = $wo->completed_at->copy()->addDays($warrantyDays);
        $this->assertEquals($expected->format('Y-m-d'), $wo->warranty_until->format('Y-m-d'));
    }

    public function test_warranty_until_null_when_not_completed(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'completed_at' => null,
        ]);
        $this->assertNull($wo->warranty_until);
    }

    public function test_is_under_warranty_true_within_period(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'completed_at' => now()->subDays(10),
        ]);
        $this->assertTrue($wo->is_under_warranty);
    }

    public function test_is_under_warranty_false_after_period(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'completed_at' => now()->subDays(200),
        ]);
        $this->assertFalse($wo->is_under_warranty);
    }

    // ── estimatedProfit accessor ──

    public function test_estimated_profit_structure(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '10000.00',
            'displacement_value' => '200.00',
        ]);
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 2,
            'cost_price' => '100.00',
            'total' => '500.00',
        ]);

        $profit = $wo->estimated_profit;

        $this->assertArrayHasKey('revenue', $profit);
        $this->assertArrayHasKey('costs', $profit);
        $this->assertArrayHasKey('profit', $profit);
        $this->assertArrayHasKey('margin_pct', $profit);
        $this->assertArrayHasKey('breakdown', $profit);
        $this->assertEquals('10000.00', $profit['revenue']);
    }

    public function test_estimated_profit_calculates_items_cost(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '2000.00',
            'displacement_value' => '0.00',
        ]);
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 5,
            'cost_price' => '100.00',
            'total' => '1000.00',
        ]);

        $profit = $wo->estimated_profit;

        $this->assertEquals('500.00', $profit['breakdown']['items_cost']);
    }

    public function test_estimated_profit_includes_commission_5_percent(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '10000.00',
            'displacement_value' => '0.00',
        ]);

        $profit = $wo->estimated_profit;

        $this->assertEquals('500.00', $profit['breakdown']['commission']);
    }

    public function test_estimated_profit_margin_percentage(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '1000.00',
            'displacement_value' => '0.00',
        ]);

        $profit = $wo->estimated_profit;

        $this->assertIsFloat($profit['margin_pct']);
        // With no items cost, costs = commission (50.00), profit = 950.00, margin = 95%
        $this->assertEquals(95.0, $profit['margin_pct']);
    }

    // ── Constants ──

    public function test_all_statuses_defined(): void
    {
        $this->assertCount(17, WorkOrder::STATUSES);
    }

    public function test_all_priorities_defined(): void
    {
        $this->assertArrayHasKey(WorkOrder::PRIORITY_LOW, WorkOrder::PRIORITIES);
        $this->assertArrayHasKey(WorkOrder::PRIORITY_NORMAL, WorkOrder::PRIORITIES);
        $this->assertArrayHasKey(WorkOrder::PRIORITY_HIGH, WorkOrder::PRIORITIES);
        $this->assertArrayHasKey(WorkOrder::PRIORITY_URGENT, WorkOrder::PRIORITIES);
    }

    public function test_service_types_defined(): void
    {
        $this->assertArrayHasKey('calibracao', WorkOrder::SERVICE_TYPES);
        $this->assertArrayHasKey('preventiva', WorkOrder::SERVICE_TYPES);
        $this->assertArrayHasKey('manutencao_corretiva', WorkOrder::SERVICE_TYPES);
    }

    public function test_agreed_payment_methods_defined(): void
    {
        $this->assertArrayHasKey('pix', WorkOrder::AGREED_PAYMENT_METHODS);
        $this->assertArrayHasKey('boleto', WorkOrder::AGREED_PAYMENT_METHODS);
        $this->assertArrayHasKey('dinheiro', WorkOrder::AGREED_PAYMENT_METHODS);
    }

    public function test_lead_sources_defined(): void
    {
        $this->assertArrayHasKey('prospeccao', WorkOrder::LEAD_SOURCES);
        $this->assertArrayHasKey('retorno', WorkOrder::LEAD_SOURCES);
        $this->assertArrayHasKey('indicacao', WorkOrder::LEAD_SOURCES);
    }

    // ── Casts ──

    public function test_tags_cast_to_array(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'tags' => ['urgente', 'calibracao'],
        ]);
        $wo->refresh();
        $this->assertIsArray($wo->tags);
        $this->assertContains('urgente', $wo->tags);
    }

    public function test_total_cast_to_decimal(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '1234.56',
        ]);
        $this->assertEquals('1234.56', $wo->total);
    }

    public function test_is_master_cast_to_boolean(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_master' => true,
        ]);
        $this->assertTrue($wo->is_master);
    }

    public function test_is_warranty_cast_to_boolean(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => true,
        ]);
        $this->assertTrue($wo->is_warranty);
    }

    // ── Relationships ──

    public function test_parent_child_relationship(): void
    {
        $parent = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_master' => true,
        ]);
        $child = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'parent_id' => $parent->id,
        ]);
        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_technicians_many_to_many(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo->technicians()->attach($tech->id, ['role' => 'technician']);

        $this->assertEquals(1, $wo->technicians()->count());
        $this->assertEquals('technician', $wo->technicians->first()->pivot->role);
    }

    public function test_equipments_list_many_to_many(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $wo->equipmentsList()->attach($eq->id, ['observations' => 'Revisão geral']);

        $this->assertEquals(1, $wo->equipmentsList()->count());
        $this->assertEquals('Revisão geral', $wo->equipmentsList->first()->pivot->observations);
    }

    // ── nextNumber() ──

    public function test_next_number_generates_sequentially(): void
    {
        $n1 = WorkOrder::nextNumber($this->tenant->id);
        $n2 = WorkOrder::nextNumber($this->tenant->id);

        $this->assertNotEquals($n1, $n2);
        $this->assertStringStartsWith('OS-', $n1);
        $this->assertStringStartsWith('OS-', $n2);
    }

    // ── statuses() ──

    public function test_statuses_method_returns_array_from_enum(): void
    {
        $statuses = WorkOrder::statuses();
        $this->assertIsArray($statuses);
        $this->assertArrayHasKey('open', $statuses);
        $this->assertArrayHasKey('label', $statuses['open']);
        $this->assertArrayHasKey('color', $statuses['open']);
    }
}
