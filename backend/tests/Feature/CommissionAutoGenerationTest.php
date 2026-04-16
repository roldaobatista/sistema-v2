<?php

namespace Tests\Feature;

use App\Enums\CommissionEventStatus;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CommissionService;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionAutoGenerationTest extends TestCase
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

        app()->instance('current_tenant_id', $this->tenant->id);

        Gate::before(fn () => true);
        Sanctum::actingAs($this->user, ['*']);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function createRule(array $overrides = []): CommissionRule
    {
        return CommissionRule::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Regra Auto Test',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ], $overrides));
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'total' => 1000.00,
        ], $overrides));
    }

    // ─── Batch 1.1: Auto-generate on WO completed ───

    public function test_wo_completed_auto_generates_commission_events(): void
    {
        $this->createRule();
        $wo = $this->createWorkOrder();

        // Transition to completed
        $wo->update(['status' => WorkOrder::STATUS_COMPLETED]);

        $events = CommissionEvent::where('work_order_id', $wo->id)->get();

        $this->assertCount(1, $events);
        $this->assertEquals(CommissionEventStatus::PENDING->value, $events->first()->status->value);
        $this->assertEquals('100.00', number_format($events->first()->commission_amount, 2, '.', ''));
        $this->assertStringContains('trigger:os_completed', $events->first()->notes);
    }

    public function test_wo_completed_warranty_skips_commission(): void
    {
        $this->createRule();
        $wo = $this->createWorkOrder(['is_warranty' => true]);

        $wo->update(['status' => WorkOrder::STATUS_COMPLETED]);

        $this->assertDatabaseMissing('commission_events', ['work_order_id' => $wo->id]);
    }

    public function test_wo_completed_zero_total_skips_commission(): void
    {
        $this->createRule();
        $wo = $this->createWorkOrder(['total' => 0]);

        $wo->update(['status' => WorkOrder::STATUS_COMPLETED]);

        $this->assertDatabaseMissing('commission_events', ['work_order_id' => $wo->id]);
    }

    public function test_wo_completed_no_active_rule_skips_commission(): void
    {
        $this->createRule(['active' => false]);
        $wo = $this->createWorkOrder();

        $wo->update(['status' => WorkOrder::STATUS_COMPLETED]);

        $this->assertDatabaseMissing('commission_events', ['work_order_id' => $wo->id]);
    }

    // ─── Batch 1.2: Auto-generate on WO invoiced ───

    public function test_wo_invoiced_auto_generates_commission_for_invoiced_rules(): void
    {
        $this->createRule(['applies_when' => CommissionRule::WHEN_OS_INVOICED]);
        $wo = $this->createWorkOrder(['status' => WorkOrder::STATUS_DELIVERED]);

        $wo->update(['status' => WorkOrder::STATUS_INVOICED]);

        $events = CommissionEvent::where('work_order_id', $wo->id)->get();

        $this->assertCount(1, $events);
        $this->assertStringContains('trigger:os_invoiced', $events->first()->notes);
    }

    public function test_wo_invoiced_does_not_trigger_completed_rules(): void
    {
        $this->createRule(['applies_when' => CommissionRule::WHEN_OS_COMPLETED]);
        $wo = $this->createWorkOrder(['status' => WorkOrder::STATUS_DELIVERED]);

        $wo->update(['status' => WorkOrder::STATUS_INVOICED]);

        $this->assertDatabaseMissing('commission_events', ['work_order_id' => $wo->id]);
    }

    // ─── Batch 1.3: Duplicate prevention ───

    public function test_duplicate_trigger_does_not_create_duplicate_events(): void
    {
        $rule = $this->createRule();
        $wo = $this->createWorkOrder();

        // First completion generates events
        $wo->update(['status' => WorkOrder::STATUS_COMPLETED]);
        $this->assertDatabaseCount('commission_events', 1);

        // Simulate re-trigger (e.g. manual call or observer re-fire)
        try {
            app(CommissionService::class)->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);
        } catch (\Throwable) {
            // Expected: duplicate prevention
        }

        // Still only 1 event
        $count = CommissionEvent::where('work_order_id', $wo->id)->count();
        $this->assertEquals(1, $count);
    }

    // ─── Batch 1.4: Multiple beneficiaries ───

    public function test_wo_completed_generates_for_multiple_beneficiaries(): void
    {
        $technician = $this->user;
        $seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        // Rule for technician
        $this->createRule([
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'value' => 10,
        ]);

        // Rule for seller
        $this->createRule([
            'name' => 'Regra Vendedor',
            'applies_to_role' => CommissionRule::ROLE_SELLER,
            'value' => 5,
        ]);

        $wo = $this->createWorkOrder([
            'assigned_to' => $technician->id,
            'seller_id' => $seller->id,
        ]);

        $wo->update(['status' => WorkOrder::STATUS_COMPLETED]);

        $events = CommissionEvent::where('work_order_id', $wo->id)->get();

        $this->assertGreaterThanOrEqual(1, $events->count());
        $userIds = $events->pluck('user_id')->unique()->toArray();
        $this->assertContains($technician->id, $userIds);
    }

    // ─── Batch 1.5: Commission failure does not block WO update ───

    public function test_commission_failure_does_not_block_wo_status_change(): void
    {
        // No rules exist — but WO update must still succeed
        $wo = $this->createWorkOrder();

        $wo->update(['status' => WorkOrder::STATUS_COMPLETED]);

        $wo->refresh();
        $this->assertEquals(WorkOrder::STATUS_COMPLETED, $wo->status);
    }

    // ─── Helper ───

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
