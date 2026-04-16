<?php

namespace Tests\Feature;

use App\Enums\CommissionEventStatus;
use App\Enums\CommissionSettlementStatus;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommissionSettlementAuthorizationTest extends TestCase
{
    private Tenant $tenant;

    private User $requester;

    private CommissionSettlement $settlement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->requester = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'commissions.settlement.create',
            'commissions.settlement.update',
            'commissions.settlement.approve',
            'commissions.settlement.view',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->settlement = $this->createSettlement();
    }

    public function test_user_with_settlement_create_can_close_period_without_update_permission(): void
    {
        $closer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->seedApprovedEventForUser($closer);
        $closer->givePermissionTo('commissions.settlement.create');

        Sanctum::actingAs($closer, ['*']);

        $this->postJson('/api/v1/commission-settlements/close', [
            'user_id' => $closer->id,
            'period' => now()->format('Y-m'),
        ])->assertCreated();
    }

    public function test_user_with_settlement_update_can_pay_without_create_permission(): void
    {
        $this->requester->givePermissionTo('commissions.settlement.update');

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson("/api/v1/commission-settlements/{$this->settlement->id}/pay", [
            'payment_notes' => 'Pagamento integral via autorizacao correta.',
        ])->assertOk();
    }

    public function test_user_with_only_settlement_create_cannot_pay(): void
    {
        $this->requester->givePermissionTo('commissions.settlement.create');

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson("/api/v1/commission-settlements/{$this->settlement->id}/pay", [
            'payment_notes' => 'Tentativa com permissao errada.',
        ])->assertForbidden();
    }

    public function test_user_with_settlement_update_can_reopen_without_create_permission(): void
    {
        $approvedSettlement = $this->createSettlement(CommissionSettlementStatus::APPROVED, now()->subMonthNoOverflow()->format('Y-m'));
        $this->requester->givePermissionTo('commissions.settlement.update');

        Sanctum::actingAs($this->requester, ['*']);

        $response = $this->postJson("/api/v1/commission-settlements/{$approvedSettlement->id}/reopen");
        $response->assertOk();
    }

    public function test_user_with_only_settlement_create_cannot_reopen(): void
    {
        $approvedSettlement = $this->createSettlement(CommissionSettlementStatus::APPROVED, now()->subMonthNoOverflow()->format('Y-m'));
        $this->requester->givePermissionTo('commissions.settlement.create');

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson("/api/v1/commission-settlements/{$approvedSettlement->id}/reopen")
            ->assertForbidden();
    }

    private function createSettlement(
        CommissionSettlementStatus $status = CommissionSettlementStatus::CLOSED,
        ?string $period = null
    ): CommissionSettlement {
        $event = $this->seedApprovedEventForUser($this->requester);

        $settlement = CommissionSettlement::updateOrCreate([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->requester->id,
            'period' => $period ?? now()->format('Y-m'),
        ], [
            'total_amount' => 100,
            'events_count' => 1,
            'status' => $status,
        ]);

        $event->update([
            'status' => $status === CommissionSettlementStatus::APPROVED
                ? CommissionEventStatus::APPROVED
                : CommissionEventStatus::PAID,
            'settlement_id' => $settlement->id,
        ]);

        if ($status === CommissionSettlementStatus::APPROVED) {
            $settlement->update([
                'paid_amount' => 0,
                'payment_notes' => null,
            ]);
        }

        return $settlement->fresh();
    }

    private function seedApprovedEventForUser(User $beneficiary): CommissionEvent
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->requester->id,
            'assigned_to' => $beneficiary->id,
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
            'completed_at' => now()->startOfMonth()->addDay(),
            'received_at' => now()->startOfMonth(),
            'total' => 1000,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $beneficiary->id,
            'name' => 'Settlement authorization rule',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        return CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $beneficiary->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEventStatus::APPROVED,
        ]);
    }
}
