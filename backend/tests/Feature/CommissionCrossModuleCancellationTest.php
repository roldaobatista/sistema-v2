<?php

namespace Tests\Feature;

use App\Enums\CommissionEventStatus;
use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionCrossModuleCancellationTest extends TestCase
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

        Gate::before(fn ($user, $ability) => true);

        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_canceling_account_receivable_cancels_pending_commission()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'total' => 1000,
        ]);

        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING->value,
            'due_date' => now()->addDays(10),
            'description' => 'Conta test',
            'origin_type' => 'work_order',
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra teste cancelamento',
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
            'account_receivable_id' => $ar->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEventStatus::PENDING->value,
        ]);

        $ar->update(['status' => FinancialStatus::CANCELLED->value]);

        $event->refresh();
        $this->assertEquals(CommissionEventStatus::CANCELLED, $event->status);
        $this->assertStringContainsString('Cancelado auto via Financeiro', $event->notes);
    }

    public function test_canceling_account_receivable_creates_reversal_for_paid_commission()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'total' => 1000,
        ]);

        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING->value,
            'due_date' => now()->addDays(10),
            'description' => 'Conta test 2',
            'origin_type' => 'work_order',
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Regra teste estorno',
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
            'account_receivable_id' => $ar->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEventStatus::PAID->value,
        ]);

        $ar->update(['status' => FinancialStatus::CANCELLED->value]);

        $event->refresh();
        $this->assertEquals(CommissionEventStatus::REVERSED, $event->status);

        $reversal = CommissionEvent::where('work_order_id', $workOrder->id)
            ->where('status', CommissionEventStatus::APPROVED->value)
            ->where('commission_amount', '<', 0)
            ->first();

        $this->assertNotNull($reversal);
        $this->assertEquals(-100, $reversal->commission_amount);
        $this->assertStringContainsString('Estorno automático', $reversal->notes);
    }
}
