<?php

namespace Tests\Unit\Models;

use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmDealProduct;
use App\Models\CrmDealStageHistory;
use App\Models\CrmFollowUpTask;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CrmDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private CrmPipeline $pipeline;

    private CrmPipelineStage $stage;

    private CrmDeal $deal;

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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
        ]);
        $this->deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── Pipeline ──

    public function test_pipeline_has_many_stages(): void
    {
        CrmPipelineStage::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
        ]);
        $this->assertGreaterThanOrEqual(4, $this->pipeline->stages()->count());
    }

    public function test_pipeline_has_many_deals(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->pipeline->deals()->count());
    }

    public function test_pipeline_soft_deletes(): void
    {
        $this->pipeline->delete();
        $this->assertNotNull(CrmPipeline::withTrashed()->find($this->pipeline->id));
    }

    public function test_pipeline_ordered_stages(): void
    {
        $s1 = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
            'order' => 1,
        ]);
        $s2 = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
            'order' => 2,
        ]);
        $stages = $this->pipeline->stages()->orderBy('order')->get();
        $this->assertTrue($stages->first()->order <= $stages->last()->order);
    }

    // ── Stage ──

    public function test_stage_belongs_to_pipeline(): void
    {
        $this->assertInstanceOf(CrmPipeline::class, $this->stage->pipeline);
    }

    public function test_stage_has_many_deals(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->stage->deals()->count());
    }

    public function test_stage_with_order(): void
    {
        $this->stage->update(['order' => 5]);
        $this->assertEquals(5, $this->stage->fresh()->order);
    }

    // ── Deal ──

    public function test_deal_belongs_to_customer(): void
    {
        $this->assertInstanceOf(Customer::class, $this->deal->customer);
    }

    public function test_deal_belongs_to_pipeline(): void
    {
        $this->assertInstanceOf(CrmPipeline::class, $this->deal->pipeline);
    }

    public function test_deal_belongs_to_stage(): void
    {
        $this->assertInstanceOf(CrmPipelineStage::class, $this->deal->stage);
    }

    public function test_deal_status_open(): void
    {
        $this->deal->update(['status' => CrmDeal::STATUS_OPEN]);
        $this->assertEquals(CrmDeal::STATUS_OPEN, $this->deal->fresh()->status);
    }

    public function test_deal_status_won(): void
    {
        $this->deal->update(['status' => CrmDeal::STATUS_WON, 'won_at' => now()]);
        $this->assertEquals(CrmDeal::STATUS_WON, $this->deal->fresh()->status);
    }

    public function test_deal_status_lost(): void
    {
        $this->deal->update(['status' => CrmDeal::STATUS_LOST, 'lost_at' => now(), 'lost_reason' => 'Preço']);
        $this->assertEquals(CrmDeal::STATUS_LOST, $this->deal->fresh()->status);
        $this->assertEquals('Preço', $this->deal->fresh()->lost_reason);
    }

    public function test_deal_value_cast(): void
    {
        $this->deal->update(['value' => '50000.00']);
        $this->assertEquals('50000.00', $this->deal->fresh()->value);
    }

    public function test_deal_probability(): void
    {
        $this->deal->update(['probability' => 75]);
        $this->assertEquals(75, $this->deal->fresh()->probability);
    }

    public function test_deal_weighted_value(): void
    {
        $this->deal->update(['value' => '100000.00', 'probability' => 50]);
        $this->deal->refresh();
        $weighted = $this->deal->value * ($this->deal->probability / 100);
        $this->assertEquals(50000.00, $weighted);
    }

    public function test_deal_soft_deletes(): void
    {
        $this->deal->delete();
        $this->assertNotNull(CrmDeal::withTrashed()->find($this->deal->id));
    }

    // ── Deal Activities ──

    public function test_deal_has_many_activities(): void
    {
        CrmActivity::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $this->deal->id,
        ]);
        $this->assertGreaterThanOrEqual(1, $this->deal->activities()->count());
    }

    public function test_activity_belongs_to_deal(): void
    {
        $activity = CrmActivity::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $this->deal->id,
        ]);
        $this->assertInstanceOf(CrmDeal::class, $activity->deal);
    }

    public function test_activity_types(): void
    {
        $types = ['call', 'email', 'meeting', 'note'];
        foreach ($types as $type) {
            $activity = CrmActivity::factory()->create([
                'tenant_id' => $this->tenant->id,
                'deal_id' => $this->deal->id,
                'type' => $type,
            ]);
            $this->assertEquals($type, $activity->type);
        }
    }

    // ── Deal Products ──

    public function test_deal_has_products(): void
    {
        CrmDealProduct::factory()->create([
            'deal_id' => $this->deal->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertGreaterThanOrEqual(1, $this->deal->products()->count());
    }

    // ── Stage History ──

    public function test_deal_stage_history_created(): void
    {
        $history = CrmDealStageHistory::create([
            'deal_id' => $this->deal->id,
            'tenant_id' => $this->tenant->id,
            'from_stage_id' => $this->stage->id,
            'to_stage_id' => $this->stage->id,
            'changed_by' => $this->user->id,
        ]);
        $this->assertNotNull($history);
    }

    // ── Follow Up Tasks ──

    public function test_deal_follow_up_creation(): void
    {
        $task = CrmFollowUpTask::factory()->create([
            'deal_id' => $this->deal->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertNotNull($task);
    }

    // ── Scopes ──

    public function test_scope_deals_by_status(): void
    {
        $this->deal->update(['status' => CrmDeal::STATUS_OPEN]);
        $open = CrmDeal::where('status', CrmDeal::STATUS_OPEN)->get();
        $this->assertTrue($open->contains('id', $this->deal->id));
    }

    public function test_scope_deals_by_pipeline(): void
    {
        $deals = CrmDeal::where('pipeline_id', $this->pipeline->id)->get();
        $this->assertTrue($deals->contains('id', $this->deal->id));
    }

    public function test_scope_high_value_deals(): void
    {
        $this->deal->update(['value' => '500000.00']);
        $highValue = CrmDeal::where('value', '>=', 100000)->get();
        $this->assertTrue($highValue->contains('id', $this->deal->id));
    }

    public function test_scope_deals_closing_this_month(): void
    {
        $this->deal->update(['expected_close_date' => now()]);
        $deals = CrmDeal::whereMonth('expected_close_date', now()->month)
            ->whereYear('expected_close_date', now()->year)->get();
        $this->assertTrue($deals->contains('id', $this->deal->id));
    }
}
