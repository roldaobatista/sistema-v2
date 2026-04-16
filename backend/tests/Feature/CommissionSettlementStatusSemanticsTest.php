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
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommissionSettlementStatusSemanticsTest extends TestCase
{
    private Tenant $tenant;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->setTenantContext($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'commissions.settlement.view',
            'commissions.settlement.update',
            'commissions.settlement.approve',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->manager->givePermissionTo([
            'commissions.settlement.view',
            'commissions.settlement.update',
            'commissions.settlement.approve',
        ]);

        Sanctum::actingAs($this->manager, ['*']);
    }

    public function test_closed_filter_includes_legacy_pending_approval_rows(): void
    {
        $closed = $this->createSettlement(CommissionSettlementStatus::CLOSED);
        $legacyPendingApproval = $this->createLegacyPendingApprovalSettlement();
        $approved = $this->createSettlement(CommissionSettlementStatus::APPROVED);

        $response = $this->getJson('/api/v1/commission-settlements?status=closed');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($closed->id, $ids);
        $this->assertContains($legacyPendingApproval->id, $ids);
        $this->assertNotContains($approved->id, $ids);
    }

    public function test_approve_accepts_legacy_pending_approval_as_closed_alias(): void
    {
        $legacyPendingApproval = $this->createLegacyPendingApprovalSettlement();

        $this->postJson("/api/v1/commission-settlements/{$legacyPendingApproval->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true])
            ->assertOk()
            ->assertJsonPath('data.status', CommissionSettlementStatus::APPROVED->value);
    }

    public function test_pay_accepts_legacy_pending_approval_as_closed_alias(): void
    {
        $legacyPendingApproval = $this->createLegacyPendingApprovalSettlement();

        $this->postJson("/api/v1/commission-settlements/{$legacyPendingApproval->id}/pay", [
            'payment_notes' => 'Pagamento de fechamento legado aguardando aprovacao.',
        ])->assertOk()
            ->assertJsonPath('data.status', CommissionSettlementStatus::PAID->value);
    }

    private function createSettlement(CommissionSettlementStatus $status): CommissionSettlement
    {
        $beneficiary = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->manager->id,
            'assigned_to' => $beneficiary->id,
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
            'completed_at' => now()->startOfMonth()->addDay(),
            'received_at' => now()->startOfMonth(),
            'total' => 1000,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $beneficiary->id,
            'name' => 'Settlement semantics rule',
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
            'user_id' => $beneficiary->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEventStatus::APPROVED,
        ]);

        $settlement = CommissionSettlement::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $beneficiary->id,
            'period' => now()->format('Y-m'),
            'total_amount' => 100,
            'events_count' => 1,
            'status' => $status,
        ]);

        $event->update(['settlement_id' => $settlement->id]);

        return $settlement->fresh();
    }

    private function createLegacyPendingApprovalSettlement(): CommissionSettlement
    {
        $settlement = $this->createSettlement(CommissionSettlementStatus::CLOSED);

        DB::table('commission_settlements')
            ->where('id', $settlement->id)
            ->update([
                'status' => CommissionSettlementStatus::PENDING_APPROVAL->value,
                'updated_at' => now(),
            ]);

        return $settlement->fresh();
    }
}
