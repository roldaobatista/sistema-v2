<?php

namespace Tests\Feature;

use App\Enums\CommissionEventStatus;
use App\Enums\ExpenseStatus;
use App\Enums\RecurringCommissionStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\RecurringCommission;
use App\Models\RecurringContract;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

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

        app()->instance('current_tenant_id', $this->tenant->id);

        // Ignora toda autorização do Spatie e Policy para isolar o teste do comissionamento
        Gate::before(function ($user, $ability) {
            return true;
        });

        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_store_rule_rejects_user_from_other_tenant(): void
    {
        $foreignUser = User::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id,
            'current_tenant_id' => null,
        ]);

        $response = $this->postJson('/api/v1/commission-rules', [
            'user_id' => $foreignUser->id,
            'name' => 'Regra Invalida',
            'value' => 10,
            'calculation_type' => 'percent_gross',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_close_settlement_rejects_user_from_other_tenant(): void
    {
        $foreignUser = User::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id,
            'current_tenant_id' => null,
        ]);

        $response = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $foreignUser->id,
            'period' => now()->format('Y-m'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_store_rule_accepts_shared_tenant_user_via_membership(): void
    {
        $sharedUser = User::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id,
            'current_tenant_id' => null,
        ]);
        $sharedUser->tenants()->attach($this->tenant->id, ['is_default' => false]);

        $response = $this->postJson('/api/v1/commission-rules', [
            'user_id' => $sharedUser->id,
            'name' => 'Regra usuario compartilhado',
            'value' => 10,
            'calculation_type' => 'percent_gross',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $sharedUser->id);
    }

    public function test_close_settlement_accepts_shared_tenant_user_via_membership(): void
    {
        $sharedUser = User::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id,
            'current_tenant_id' => null,
        ]);
        $sharedUser->tenants()->attach($this->tenant->id, ['is_default' => false]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $sharedUser->id,
            'completed_at' => now()->startOfMonth()->addDays(2),
            'received_at' => now()->startOfMonth(),
            'total' => 1000,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $sharedUser->id,
            'name' => 'Regra usuario compartilhado',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $sharedUser->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $response = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $sharedUser->id,
            'period' => now()->format('Y-m'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $sharedUser->id)
            ->assertJsonPath('data.events_count', 1);
    }

    public function test_commission_users_lists_primary_and_shared_tenant_members(): void
    {
        $sharedUser = User::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id,
            'current_tenant_id' => null,
            'name' => 'Usuario Compartilhado',
        ]);
        $sharedUser->tenants()->attach($this->tenant->id, ['is_default' => false]);

        $response = $this->getJson('/api/v1/commission-users');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $this->user->id,
                'name' => $this->user->name,
            ])
            ->assertJsonFragment([
                'id' => $sharedUser->id,
                'name' => 'Usuario Compartilhado',
            ]);
    }

    public function test_work_order_completed_generates_commission_events_from_active_rules(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
            'total' => 1000,
            'os_number' => 'BLOCO-5501',
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Comissao padrao',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/status", [
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('commission_events', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'status' => CommissionEvent::STATUS_PENDING,
            'commission_amount' => 100.00,
        ]);
    }

    public function test_commission_settlement_close_and_pay_have_consistent_status_flow(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'total' => 1000,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra fechamento mensal',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $period = now()->format('Y-m');

        $close = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $this->user->id,
            'period' => $period,
        ]);

        $close->assertStatus(201)
            ->assertJsonPath('data.status', CommissionSettlement::STATUS_CLOSED);

        // After closing, events should remain APPROVED (not prematurely set to PAID)
        $event->refresh();
        $this->assertSame(CommissionEventStatus::APPROVED, $event->status);

        $settlementId = $close->json('data.id');
        $pay = $this->postJson("/api/v1/commission-settlements/{$settlementId}/pay");

        $pay->assertOk()
            ->assertJsonPath('data.status', CommissionSettlement::STATUS_PAID);

        // Only after payment should events become PAID
        $event->refresh();
        $this->assertSame(CommissionEventStatus::PAID, $event->status);
        $this->assertNotNull($pay->json('data.paid_at'));
    }

    public function test_close_settlement_recalculates_existing_period_with_already_linked_events_and_new_approvals(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $workOrderA = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'completed_at' => now()->startOfMonth()->addDays(1),
            'received_at' => now()->startOfMonth(),
            'total' => 1000,
        ]);

        $workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'completed_at' => now()->startOfMonth()->addDays(3),
            'received_at' => now()->startOfMonth(),
            'total' => 500,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra recalc settlement',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $firstEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrderA->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $period = now()->format('Y-m');

        $firstClose = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $this->user->id,
            'period' => $period,
        ]);

        $firstClose->assertCreated()
            ->assertJsonPath('data.total_amount', '100.00')
            ->assertJsonPath('data.events_count', 1);

        $settlementId = $firstClose->json('data.id');

        $secondEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrderB->id,
            'user_id' => $this->user->id,
            'base_amount' => 500,
            'commission_amount' => 50,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $secondClose = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $this->user->id,
            'period' => $period,
        ]);

        $secondClose->assertCreated()
            ->assertJsonPath('data.id', $settlementId)
            ->assertJsonPath('data.total_amount', '150.00')
            ->assertJsonPath('data.events_count', 2);

        $this->assertDatabaseHas('commission_events', [
            'id' => $firstEvent->id,
            'settlement_id' => $settlementId,
        ]);
        $this->assertDatabaseHas('commission_events', [
            'id' => $secondEvent->id,
            'settlement_id' => $settlementId,
        ]);
    }

    public function test_updating_linked_event_status_recalculates_settlement_totals(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'completed_at' => now()->startOfMonth()->addDays(2),
            'received_at' => now()->startOfMonth(),
            'total' => 1000,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra recalc status',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $settlementId = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $this->user->id,
            'period' => now()->format('Y-m'),
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/commission-events/{$event->id}/status", [
            'status' => CommissionEvent::STATUS_PENDING,
        ])->assertOk();

        $this->assertDatabaseHas('commission_settlements', [
            'id' => $settlementId,
            'total_amount' => 0,
            'events_count' => 0,
        ]);
        $this->assertDatabaseHas('commission_events', [
            'id' => $event->id,
            'settlement_id' => null,
        ]);
    }

    public function test_pay_settlement_rejects_partial_payment_amount(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'total' => 1000,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra bloqueio parcial settlement',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $settlementId = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $this->user->id,
            'period' => now()->format('Y-m'),
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/commission-settlements/{$settlementId}/pay", [
            'paid_amount' => 50,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Pagamento parcial de fechamento de comissao nao e suportado');

        $event->refresh();
        $this->assertSame(CommissionEventStatus::APPROVED, $event->status);
        $this->assertDatabaseMissing('accounts_payable', [
            'tenant_id' => $this->tenant->id,
            'notes' => "commission_settlement:{$settlementId}",
        ]);
    }

    public function test_pay_settlement_reuses_existing_account_payable_linked_to_same_settlement(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'total' => 1000,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra AP idempotente',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $settlementId = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $this->user->id,
            'period' => now()->format('Y-m'),
        ])->assertStatus(201)->json('data.id');

        AccountPayable::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Lancamento legado de fechamento',
            'amount' => 1,
            'amount_paid' => 0,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => "commission_settlement:{$settlementId}",
        ]);

        $this->postJson("/api/v1/commission-settlements/{$settlementId}/pay", [
            'payment_method' => 'pix',
            'payment_notes' => 'Pago via tesouraria',
        ])->assertOk();

        $this->assertDatabaseCount('accounts_payable', 1);
        $this->assertDatabaseHas('accounts_payable', [
            'tenant_id' => $this->tenant->id,
            'notes' => "commission_settlement:{$settlementId}",
            'amount' => 100,
            'amount_paid' => 100,
            'payment_method' => 'pix',
            'status' => 'paid',
        ]);
    }

    public function test_commission_events_accept_os_number_filter(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $workOrderA = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'BLOCO-COM-001',
            'number' => 'OS-1001',
        ]);
        $workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'BLOCO-COM-999',
            'number' => 'OS-1002',
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Filtro OS',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrderA->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);
        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrderB->id,
            'user_id' => $this->user->id,
            'base_amount' => 1200,
            'commission_amount' => 120,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);

        $this->getJson('/api/v1/commission-events?os_number=BLOCO-COM-001')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.work_order.os_number', 'BLOCO-COM-001');
    }

    public function test_commission_disputes_accept_os_number_filter_and_return_identifier(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $workOrderA = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'BLOCO-DSP-01',
            'number' => 'OS-8101',
        ]);
        $workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'BLOCO-DSP-99',
            'number' => 'OS-8199',
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra disputa',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $eventA = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrderA->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);
        $eventB = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrderB->id,
            'user_id' => $this->user->id,
            'base_amount' => 1200,
            'commission_amount' => 120,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);

        DB::table('commission_disputes')->insert([
            [
                'tenant_id' => $this->tenant->id,
                'commission_event_id' => $eventA->id,
                'user_id' => $this->user->id,
                'reason' => 'Divergencia no calculo da OS A',
                'status' => 'open', // Raw DB insert: model constant n/a for disputes
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->tenant->id,
                'commission_event_id' => $eventB->id,
                'user_id' => $this->user->id,
                'reason' => 'Divergencia no calculo da OS B',
                'status' => 'open', // Raw DB insert: model constant n/a for disputes
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/v1/commission-disputes?os_number=BLOCO-DSP-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.commission_event.work_order.os_number', 'BLOCO-DSP-01');
    }

    public function test_my_commission_disputes_returns_only_authenticated_user_disputes(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
            'name' => 'Disputas proprias',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $ownEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);

        $otherEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $otherUser->id,
            'base_amount' => 1000,
            'commission_amount' => 120,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);

        DB::table('commission_disputes')->insert([
            [
                'tenant_id' => $this->tenant->id,
                'commission_event_id' => $ownEvent->id,
                'user_id' => $this->user->id,
                'reason' => 'Minha disputa aberta para validar listagem.',
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->tenant->id,
                'commission_event_id' => $otherEvent->id,
                'user_id' => $otherUser->id,
                'reason' => 'Disputa de outro usuario.',
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/v1/my/commission-disputes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->user->id);
    }

    public function test_store_dispute_blocks_paid_commission_events(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Bloqueio disputa paga',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_PAID,
        ]);

        $this->postJson('/api/v1/commission-disputes', [
            'commission_event_id' => $event->id,
            'reason' => 'Nao deveria aceitar disputa em comissao ja paga.',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Nao e permitido abrir contestacao para comissao ja paga.');
    }

    public function test_resolve_dispute_blocks_adjustment_when_settlement_is_paid(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Bloqueio ajuste pago',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $settlement = CommissionSettlement::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => now()->format('Y-m'),
            'total_amount' => 100,
            'events_count' => 1,
            'status' => CommissionSettlement::STATUS_PAID,
            'paid_amount' => 100,
            'paid_at' => now(),
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'settlement_id' => $settlement->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_PAID,
        ]);

        $disputeId = DB::table('commission_disputes')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'commission_event_id' => $event->id,
            'user_id' => $this->user->id,
            'reason' => 'Tentativa de ajuste apos pagamento.',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson("/api/v1/commission-disputes/{$disputeId}/resolve", [
            'status' => 'accepted',
            'resolution_notes' => 'Nao pode alterar evento ja pago.',
            'new_amount' => 80,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Nao e permitido aceitar contestacao de comissao ja paga. Reabra o fechamento antes de ajustar.');
    }

    public function test_reopen_settlement_reverts_approved_events_to_pending(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'total' => 1000,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra reopen test',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $period = now()->format('Y-m');

        // Close the settlement first
        $close = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $this->user->id,
            'period' => $period,
        ]);

        $close->assertStatus(201);
        $settlementId = $close->json('data.id');

        // Reopen
        $reopen = $this->postJson("/api/v1/commission-settlements/{$settlementId}/reopen");
        $reopen->assertOk()
            ->assertJsonPath('data.status', CommissionSettlement::STATUS_OPEN);

        // Events should be reverted to pending
        $event->refresh();
        $this->assertSame(CommissionEventStatus::PENDING, $event->status);
    }

    public function test_reopen_settlement_clears_financial_residue_fields(): void
    {
        $settlement = CommissionSettlement::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => now()->format('Y-m'),
            'total_amount' => 100,
            'events_count' => 1,
            'status' => CommissionSettlement::STATUS_REJECTED,
            'paid_amount' => 100,
            'payment_notes' => 'Pagamento legado residual',
            'paid_at' => now(),
            'approved_by' => $this->user->id,
            'approved_at' => now(),
            'rejection_reason' => 'Motivo anterior',
        ]);

        $this->postJson("/api/v1/commission-settlements/{$settlement->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', CommissionSettlement::STATUS_OPEN);

        $settlement->refresh();
        $this->assertSame('0.00', $settlement->paid_amount);
        $this->assertNull($settlement->payment_notes);
        $this->assertNull($settlement->paid_at);
        $this->assertNull($settlement->rejection_reason);
    }

    public function test_batch_update_status_validates_transitions(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'total' => 500,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Batch test rule',
            'type' => 'percentage',
            'value' => 5,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $pendingEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 500,
            'commission_amount' => 25,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);

        $paidEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 500,
            'commission_amount' => 25,
            'status' => CommissionEvent::STATUS_PAID,
        ]);

        // Batch approve: pending->approved valid, paid->approved invalid
        $response = $this->postJson('/api/v1/commission-events/batch-status', [
            'ids' => [$pendingEvent->id, $paidEvent->id],
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 1);

        $pendingEvent->refresh();
        $paidEvent->refresh();

        $this->assertSame(CommissionEventStatus::APPROVED, $pendingEvent->status);
        $this->assertSame(CommissionEventStatus::PAID, $paidEvent->status); // unchanged
    }

    public function test_update_event_status_rejects_invalid_transition(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'total' => 500,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Transition test rule',
            'type' => 'percentage',
            'value' => 5,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $pendingEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 500,
            'commission_amount' => 25,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);

        // pending → paid should fail (must go through approved first)
        $response = $this->putJson("/api/v1/commission-events/{$pendingEvent->id}/status", [
            'status' => CommissionEvent::STATUS_PAID,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Transição de status inválida: pending → paid']);

        // Verify event status unchanged
        $pendingEvent->refresh();
        $this->assertSame(CommissionEventStatus::PENDING, $pendingEvent->status);

        // pending → approved should succeed
        $response = $this->putJson("/api/v1/commission-events/{$pendingEvent->id}/status", [
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $response->assertOk();
        $pendingEvent->refresh();
        $this->assertSame(CommissionEventStatus::APPROVED, $pendingEvent->status);
    }

    public function test_simulate_applies_campaign_multiplier(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'total' => 1000,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => '10% Sim Rule',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        // Create active campaign with 1.5x multiplier
        DB::table('commission_campaigns')->insert([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Campaign 1.5x',
            'multiplier' => 1.5,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/commission-simulate', [
            'work_order_id' => $workOrder->id,
        ]);

        $response->assertOk();
        $simulations = $response->json('data');

        $this->assertNotEmpty($simulations);
        $sim = $simulations[0];

        // 10% of 1000 = 100, * 1.5 campaign = 150
        $this->assertEquals(150.00, $sim['commission_amount']);
        $this->assertEquals(1.5, $sim['multiplier']);
        $this->assertEquals('Test Campaign 1.5x', $sim['campaign_name']);
    }

    public function test_commission_percent_net_only_deducts_expenses_affecting_net_value(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'total' => 1000,
        ]);

        // Expense that DOES affect net value (should be deducted)
        Expense::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'description' => 'Deductible expense',
            'amount' => 200,
            'expense_date' => now()->toDateString(),
            'status' => ExpenseStatus::APPROVED,
            'affects_net_value' => true,
        ]);

        // Expense that does NOT affect net value (should NOT be deducted)
        Expense::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'description' => 'Non-deductible expense',
            'amount' => 300,
            'expense_date' => now()->toDateString(),
            'status' => ExpenseStatus::APPROVED,
            'affects_net_value' => false,
        ]);

        // Rule: 10% of NET (gross - deductible expenses only)
        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Percent net test',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_NET,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        // calculate() should use only the 200 expense (affects_net_value=true)
        // net = 1000 - 200 = 800, commission = 800 * 10% = 80
        $commission = $rule->calculate($workOrder);

        $this->assertEquals(80.0, $commission);
    }

    public function test_reject_settlement_sets_rejected_status_and_reverts_events(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'total' => 1000,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Reject test rule',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $period = now()->format('Y-m');

        // Close the settlement
        $close = $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $this->user->id,
            'period' => $period,
        ]);

        $close->assertStatus(201);
        $settlementId = $close->json('data.id');

        // Reject the settlement
        $reject = $this->postJson("/api/v1/commission-settlements/{$settlementId}/reject", [
            'rejection_reason' => 'Valores incorretos, necessário revisão.',
        ]);

        $reject->assertOk()
            ->assertJsonPath('data.status', CommissionSettlement::STATUS_REJECTED);

        // Events should be unlinked and reverted to pending
        $event->refresh();
        $this->assertSame(CommissionEventStatus::PENDING, $event->status);
        $this->assertNull($event->settlement_id);
    }

    public function test_reject_settlement_clears_financial_residue_fields(): void
    {
        $settlement = CommissionSettlement::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => now()->format('Y-m'),
            'total_amount' => 100,
            'events_count' => 1,
            'status' => CommissionSettlement::STATUS_CLOSED,
            'paid_amount' => 100,
            'payment_notes' => 'Pagamento legado residual',
            'paid_at' => now(),
        ]);

        $this->postJson("/api/v1/commission-settlements/{$settlement->id}/reject", [
            'rejection_reason' => 'Fechamento invalido',
        ])->assertOk()
            ->assertJsonPath('data.status', CommissionSettlement::STATUS_REJECTED);

        $settlement->refresh();
        $this->assertSame('0.00', $settlement->paid_amount);
        $this->assertNull($settlement->payment_notes);
        $this->assertNull($settlement->paid_at);
        $this->assertSame('Fechamento invalido', $settlement->rejection_reason);
    }

    public function test_export_settlements_respects_user_and_period_filters(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        CommissionSettlement::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => '2026-02',
            'total_amount' => 100,
            'events_count' => 1,
            'status' => CommissionSettlement::STATUS_CLOSED,
        ]);

        CommissionSettlement::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => '2026-01',
            'total_amount' => 200,
            'events_count' => 2,
            'status' => CommissionSettlement::STATUS_CLOSED,
        ]);

        CommissionSettlement::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
            'period' => '2026-02',
            'total_amount' => 300,
            'events_count' => 3,
            'status' => CommissionSettlement::STATUS_CLOSED,
        ]);

        $response = $this->get('/api/v1/commission-settlements/export?user_id='.$this->user->id.'&period=2026-02');

        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('2026-02', $csv);
        $this->assertStringContainsString((string) $this->user->name, $csv);
        $this->assertStringNotContainsString('2026-01', $csv);
        $this->assertStringNotContainsString((string) $otherUser->name, $csv);
    }

    public function test_export_events_serializes_enum_status_without_runtime_error(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'EXP-EVT-001',
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra export event',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $response = $this->get('/api/v1/commission-events/export?os_number=EXP-EVT-001');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('EXP-EVT-001', $csv);
        $this->assertStringContainsString('approved', $csv);
    }

    public function test_download_statement_renders_pdf_with_enum_statuses(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'PDF-001',
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra PDF',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);

        $response = $this->get('/api/v1/commission-statement/pdf?user_id='.$this->user->id.'&period='.now()->format('Y-m'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_my_summary_ignores_reversed_and_cancelled_events_in_total_month(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra resumo pessoal',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        foreach ([
            [CommissionEvent::STATUS_PENDING, 10],
            [CommissionEvent::STATUS_APPROVED, 20],
            [CommissionEvent::STATUS_PAID, 30],
            [CommissionEvent::STATUS_REVERSED, 40],
            [CommissionEvent::STATUS_CANCELLED, 50],
        ] as [$status, $amount]) {
            CommissionEvent::create([
                'tenant_id' => $this->tenant->id,
                'commission_rule_id' => $rule->id,
                'work_order_id' => $workOrder->id,
                'user_id' => $this->user->id,
                'base_amount' => 1000,
                'commission_amount' => $amount,
                'status' => $status,
            ]);
        }

        $response = $this->getJson('/api/v1/my/commission-summary');

        $response->assertOk();
        $this->assertSame(60.0, (float) $response->json('data.total_month'));
        $this->assertSame(30.0, (float) $response->json('data.pending'));
        $this->assertSame(30.0, (float) $response->json('data.paid'));
    }

    public function test_my_summary_uses_work_order_operational_period_instead_of_event_creation_date(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'completed_at' => now()->startOfMonth()->addDays(2),
            'received_at' => now()->startOfMonth(),
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra periodo operacional',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_APPROVED,
        ]);
        $event->forceFill([
            'created_at' => now()->copy()->addMonth()->startOfMonth()->addDay(),
            'updated_at' => now()->copy()->addMonth()->startOfMonth()->addDay(),
        ])->save();

        $response = $this->getJson('/api/v1/my/commission-summary');

        $response->assertOk();
        $this->assertSame(100.0, (float) $response->json('data.total_month'));
        $this->assertSame(100.0, (float) $response->json('data.pending'));
    }

    public function test_my_summary_accepts_explicit_period_filter(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $targetPeriod = now()->copy()->subMonthNoOverflow();

        $targetWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'completed_at' => $targetPeriod->copy()->startOfMonth()->addDays(2),
            'received_at' => $targetPeriod->copy()->startOfMonth(),
        ]);

        $currentWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'completed_at' => now()->startOfMonth()->addDays(2),
            'received_at' => now()->startOfMonth(),
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra resumo por periodo',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $targetWorkOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 800,
            'commission_amount' => 80,
            'status' => CommissionEvent::STATUS_PAID,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $currentWorkOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);

        $response = $this->getJson('/api/v1/my/commission-summary?period='.$targetPeriod->format('Y-m'));

        $response->assertOk();
        $this->assertSame(80.0, (float) $response->json('data.total_month'));
        $this->assertSame(0.0, (float) $response->json('data.pending'));
        $this->assertSame(80.0, (float) $response->json('data.paid'));
    }

    public function test_my_summary_can_return_all_periods_when_requested(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $olderWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'completed_at' => now()->copy()->subMonthsNoOverflow(2)->startOfMonth()->addDays(2),
            'received_at' => now()->copy()->subMonthsNoOverflow(2)->startOfMonth(),
        ]);

        $currentWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'completed_at' => now()->startOfMonth()->addDays(2),
            'received_at' => now()->startOfMonth(),
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra resumo acumulado',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $olderWorkOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 500,
            'commission_amount' => 50,
            'status' => CommissionEvent::STATUS_PAID,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $currentWorkOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 700,
            'commission_amount' => 70,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);

        $response = $this->getJson('/api/v1/my/commission-summary?all=1');

        $response->assertOk();
        $this->assertSame(120.0, (float) $response->json('data.total_month'));
        $this->assertSame(70.0, (float) $response->json('data.pending'));
        $this->assertSame(50.0, (float) $response->json('data.paid'));
    }

    public function test_my_events_period_filter_uses_work_order_operational_period(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $currentPeriod = now()->format('Y-m');

        $matchingWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'PERIODO-OK',
            'completed_at' => now()->startOfMonth()->addDays(3),
            'received_at' => now()->startOfMonth(),
        ]);

        $outsideWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'os_number' => 'PERIODO-FORA',
            'completed_at' => now()->copy()->subMonthNoOverflow()->startOfMonth()->addDays(3),
            'received_at' => now()->copy()->subMonthNoOverflow()->startOfMonth(),
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra listagem operacional',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $matchingEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $matchingWorkOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);
        $matchingEvent->forceFill([
            'created_at' => now()->copy()->addMonth(),
            'updated_at' => now()->copy()->addMonth(),
        ])->save();

        $outsideEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $outsideWorkOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);
        $outsideEvent->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $response = $this->getJson("/api/v1/my/commission-events?period={$currentPeriod}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.work_order.os_number', 'PERIODO-OK');
    }

    public function test_store_recurring_commission_rejects_inactive_contract(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $contract = RecurringContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'is_active' => false,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra recorrente',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $this->postJson('/api/v1/recurring-commissions', [
            'user_id' => $this->user->id,
            'recurring_contract_id' => $contract->id,
            'commission_rule_id' => $rule->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'O contrato recorrente informado nao esta ativo.');
    }

    public function test_update_recurring_commission_status_rejects_invalid_transition(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $contract = RecurringContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'is_active' => true,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra recorrente encerrada',
            'type' => 'percentage',
            'value' => 8,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $recurring = RecurringCommission::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'recurring_contract_id' => $contract->id,
            'commission_rule_id' => $rule->id,
            'status' => RecurringCommissionStatus::TERMINATED,
        ]);

        $this->putJson("/api/v1/recurring-commissions/{$recurring->id}/status", [
            'status' => 'active',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Transicao de status invalida: terminated -> active');
    }
}
