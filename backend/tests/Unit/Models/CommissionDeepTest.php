<?php

namespace Tests\Unit\Models;

use App\Enums\CommissionDisputeStatus;
use App\Enums\CommissionEventStatus;
use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionCampaign;
use App\Models\CommissionDispute;
use App\Models\CommissionEvent;
use App\Models\CommissionGoal;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\RecurringCommission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CommissionDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        $this->actingAs($this->user);
    }

    // ── CommissionRule ──

    public function test_commission_rule_creation(): void
    {
        $rule = CommissionRule::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($rule);
    }

    public function test_commission_rule_percentage(): void
    {
        $rule = CommissionRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'percentage' => '10.00',
        ]);
        $this->assertEquals('10.00', $rule->percentage);
    }

    public function test_commission_rule_fixed_amount(): void
    {
        $rule = CommissionRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'fixed',
            'fixed_amount' => '200.00',
        ]);
        $this->assertEquals('fixed', $rule->type);
    }

    public function test_commission_rule_belongs_to_user(): void
    {
        $rule = CommissionRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertInstanceOf(User::class, $rule->user);
    }

    public function test_commission_rule_soft_deletes(): void
    {
        $rule = CommissionRule::factory()->create(['tenant_id' => $this->tenant->id]);
        $rule->delete();
        $this->assertNotNull(CommissionRule::withTrashed()->find($rule->id));
    }

    // ── CommissionEvent ──

    public function test_commission_event_creation(): void
    {
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertNotNull($event);
    }

    public function test_commission_event_amount(): void
    {
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'amount' => '1500.00',
        ]);
        $this->assertEquals('1500.00', $event->amount);
    }

    public function test_commission_event_belongs_to_user(): void
    {
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertInstanceOf(User::class, $event->user);
    }

    public function test_commission_event_status(): void
    {
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
        $this->assertEquals(CommissionEventStatus::PENDING, $event->status);
    }

    // ── CommissionSettlement ──

    public function test_settlement_creation(): void
    {
        $settlement = CommissionSettlement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertNotNull($settlement);
    }

    public function test_settlement_total(): void
    {
        $settlement = CommissionSettlement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'total' => '5000.00',
        ]);
        $this->assertEquals('5000.00', $settlement->total);
    }

    public function test_settlement_status_paid(): void
    {
        $settlement = CommissionSettlement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $this->assertEquals(CommissionSettlementStatus::PAID, $settlement->status);
    }

    // ── CommissionGoal ──

    public function test_goal_creation(): void
    {
        $goal = CommissionGoal::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertNotNull($goal);
    }

    public function test_goal_target_value(): void
    {
        $goal = CommissionGoal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'target_value' => '100000.00',
        ]);
        $this->assertEquals('100000.00', $goal->target_value);
    }

    public function test_goal_progress(): void
    {
        $goal = CommissionGoal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'target_value' => '100000.00',
            'current_value' => '75000.00',
        ]);
        $progress = ($goal->current_value / $goal->target_value) * 100;
        $this->assertEquals(75.0, $progress);
    }

    // ── CommissionCampaign ──

    public function test_campaign_creation(): void
    {
        $campaign = CommissionCampaign::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($campaign);
    }

    public function test_campaign_date_range(): void
    {
        $campaign = CommissionCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonths(3),
        ]);
        $this->assertTrue($campaign->starts_at->isBefore($campaign->ends_at));
    }

    public function test_campaign_is_active(): void
    {
        $campaign = CommissionCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->assertTrue($campaign->is_active);
    }

    // ── CommissionDispute ──

    public function test_dispute_creation(): void
    {
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $dispute = CommissionDispute::factory()->create([
            'commission_event_id' => $event->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertNotNull($dispute);
    }

    public function test_dispute_status_open(): void
    {
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $dispute = CommissionDispute::factory()->create([
            'commission_event_id' => $event->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);
        $this->assertEquals(CommissionDisputeStatus::OPEN, $dispute->status);
    }

    public function test_dispute_resolution(): void
    {
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $dispute = CommissionDispute::factory()->create([
            'commission_event_id' => $event->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
        $this->assertEquals(CommissionDisputeStatus::RESOLVED, $dispute->status);
        $this->assertSame('Resolvida (legado)', $dispute->status->label());
    }

    // ── RecurringCommission ──

    public function test_recurring_commission_creation(): void
    {
        $rc = RecurringCommission::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertNotNull($rc);
    }

    public function test_recurring_commission_frequency(): void
    {
        $rc = RecurringCommission::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'frequency' => 'monthly',
        ]);
        $this->assertEquals('monthly', $rc->frequency);
    }
}
