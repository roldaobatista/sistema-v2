<?php

namespace Tests\Feature;

use App\Enums\CommissionEventStatus;
use App\Enums\CommissionSettlementStatus;
use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionSettlementWorkflowTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Gate::before(fn () => true);
        Sanctum::actingAs($this->user, ['*']);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function createApprovedEvent(float $amount = 100.00): CommissionEvent
    {
        $rule = CommissionRule::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Test Rule WF'],
            [
                'type' => 'percentage',
                'value' => 10,
                'applies_to' => 'all',
                'active' => true,
                'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
                'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
                'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
            ]
        );

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'total' => 1000.00,
            'status' => WorkOrder::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000.00,
            'commission_amount' => $amount,
            'proportion' => 1.0000,
            'status' => CommissionEventStatus::APPROVED,
            'notes' => 'Test event | trigger:os_completed',
        ]);
    }

    private function closeSettlement(): CommissionSettlement
    {
        $response = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $this->user->id,
            'period' => now()->format('Y-m'),
        ]);

        $response->assertStatus(201);
        $id = $response->json('data.id');
        $this->assertNotNull($id, 'Settlement ID should exist in response');

        return CommissionSettlement::findOrFail($id);
    }

    // ─── Close ───

    public function test_close_settlement_aggregates_correct_events(): void
    {
        $this->createApprovedEvent(100.00);
        $this->createApprovedEvent(200.00);

        $settlement = $this->closeSettlement();

        $this->assertEquals(300.00, (float) $settlement->total_amount);
        $this->assertEquals(2, $settlement->events_count);
    }

    // ─── Approve ───

    public function test_settlement_approve_changes_status(): void
    {
        $this->createApprovedEvent();
        $settlement = $this->closeSettlement();

        $response = $this->postJson("/api/v1/commission-settlements/{$settlement->id}/approve");
        $response->assertOk();

        $settlement->refresh();
        $statusVal = $settlement->status instanceof CommissionSettlementStatus
            ? $settlement->status->value : (string) $settlement->status;
        $this->assertContains($statusVal, ['approved', 'pending_approval']);
    }

    // ─── Pay ───

    public function test_settlement_pay_updates_paid_at(): void
    {
        $this->createApprovedEvent();
        $settlement = $this->closeSettlement();

        // Approve first
        $this->postJson("/api/v1/commission-settlements/{$settlement->id}/approve")->assertOk();

        $response = $this->postJson("/api/v1/commission-settlements/{$settlement->id}/pay", [
            'payment_notes' => 'Pago via PIX',
        ]);
        $response->assertOk();

        $settlement->refresh();
        $statusVal = $settlement->status instanceof CommissionSettlementStatus
            ? $settlement->status->value : (string) $settlement->status;
        $this->assertEquals('paid', $statusVal);
        $this->assertNotNull($settlement->paid_at);
    }

    // ─── Reject requires reason ───

    public function test_settlement_reject_requires_reason(): void
    {
        $this->createApprovedEvent();
        $settlement = $this->closeSettlement();

        $response = $this->postJson("/api/v1/commission-settlements/{$settlement->id}/reject", []);
        $response->assertStatus(422);
    }

    // ─── Reopen ───

    public function test_settlement_reopen_resets_status(): void
    {
        $this->createApprovedEvent();
        $settlement = $this->closeSettlement();

        $response = $this->postJson("/api/v1/commission-settlements/{$settlement->id}/reopen");
        $response->assertOk();

        $settlement->refresh();
        $statusVal = $settlement->status instanceof CommissionSettlementStatus
            ? $settlement->status->value : (string) $settlement->status;
        $this->assertEquals('open', $statusVal);
    }

    // ─── AR Cancellation → Commission ───

    public function test_ar_cancellation_cancels_pending_commissions(): void
    {
        $rule = CommissionRule::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Test Rule AR'],
            [
                'type' => 'percentage', 'value' => 10, 'applies_to' => 'all', 'active' => true,
                'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
                'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
                'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
            ]
        );

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'total' => 1000.00,
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000.00,
            'commission_amount' => 100.00,
            'proportion' => 1.0000,
            'status' => CommissionEventStatus::PENDING,
            'notes' => 'Test',
        ]);

        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
            'amount' => 1000.00,
            'due_date' => now()->addDays(30),
            'status' => FinancialStatus::PENDING,
            'description' => 'Test AR',
        ]);

        $ar->update(['status' => FinancialStatus::CANCELLED]);

        $event->refresh();
        $statusVal = $event->status instanceof CommissionEventStatus
            ? $event->status->value : (string) $event->status;
        $this->assertEquals('cancelled', $statusVal);
    }

    public function test_ar_cancellation_reverses_paid_commissions(): void
    {
        $rule = CommissionRule::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Test Rule AR Rev'],
            [
                'type' => 'percentage', 'value' => 10, 'applies_to' => 'all', 'active' => true,
                'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
                'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
                'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
            ]
        );

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'total' => 1000.00,
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000.00,
            'commission_amount' => 100.00,
            'proportion' => 1.0000,
            'status' => CommissionEventStatus::PAID,
            'notes' => 'Test paid',
        ]);

        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
            'amount' => 1000.00,
            'due_date' => now()->addDays(30),
            'status' => FinancialStatus::PENDING,
            'description' => 'Test AR',
        ]);

        $ar->update(['status' => FinancialStatus::CANCELLED]);

        $event->refresh();
        $statusVal = $event->status instanceof CommissionEventStatus
            ? $event->status->value : (string) $event->status;
        $this->assertEquals('reversed', $statusVal);

        $reversal = CommissionEvent::where('work_order_id', $wo->id)
            ->where('id', '!=', $event->id)
            ->first();
        $this->assertNotNull($reversal);
        $this->assertTrue((float) $reversal->commission_amount < 0);
    }
}
