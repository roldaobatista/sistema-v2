<?php

namespace Tests\Unit\Services;

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

class CommissionServiceTest extends TestCase
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

    public function test_commission_rule_creation(): void
    {
        $rule = CommissionRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Comissão padrão calibração',
            'type' => 'percentage',
            'value' => '5.00',
        ]);

        $this->assertEquals('5.00', $rule->value);
        $this->assertEquals('percentage', $rule->type);
    }

    public function test_commission_event_ties_to_user(): void
    {
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'commission_amount' => '250.00',
        ]);

        $this->assertInstanceOf(User::class, $event->user);
        $this->assertEquals('250.00', $event->commission_amount);
    }

    public function test_commission_settlement_creation(): void
    {
        $settlement = CommissionSettlement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
        ]);

        $this->assertEquals('open', $settlement->status->value ?? $settlement->status);
    }

    public function test_commission_dispute_creation(): void
    {
        $dispute = CommissionDispute::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
        ]);

        $this->assertEquals('open', $dispute->status->value ?? $dispute->status);
    }

    public function test_commission_campaign_creation(): void
    {
        $campaign = CommissionCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Campanha Q1 2026',
        ]);

        $this->assertEquals('Campanha Q1 2026', $campaign->name);
    }

    public function test_commission_goal_creation(): void
    {
        $goal = CommissionGoal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'target_amount' => '50000.00',
        ]);

        $this->assertEquals('50000.00', $goal->target_amount);
    }

    public function test_recurring_commission_creation(): void
    {
        $recurring = RecurringCommission::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals($this->tenant->id, $recurring->tenant_id);
    }

    public function test_commission_rule_belongs_to_tenant(): void
    {
        $rule = CommissionRule::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $rule->tenant_id);
    }
}
