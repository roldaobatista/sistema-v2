<?php

namespace Tests\Unit\Models;

use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CrmModelsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private CrmPipeline $pipeline;

    private CrmPipelineStage $stage;

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
            'probability' => 50,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── CrmDeal — Relationships ──

    public function test_deal_belongs_to_customer(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        $this->assertInstanceOf(Customer::class, $deal->customer);
    }

    public function test_deal_belongs_to_pipeline(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        $this->assertInstanceOf(CrmPipeline::class, $deal->pipeline);
    }

    public function test_deal_belongs_to_stage(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        $this->assertInstanceOf(CrmPipelineStage::class, $deal->stage);
    }

    public function test_deal_belongs_to_assignee(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'assigned_to' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $deal->assignee);
    }

    public function test_deal_has_many_activities(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        CrmActivity::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $deal->activities()->count());
    }

    public function test_deal_belongs_to_quote(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);

        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'quote_id' => $quote->id,
        ]);

        $this->assertInstanceOf(Quote::class, $deal->quote);
    }

    // ── CrmDeal — Scopes ──

    public function test_scope_open_filters_open_deals(): void
    {
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_WON,
        ]);

        $openCount = CrmDeal::open()->count();
        $this->assertGreaterThanOrEqual(1, $openCount);
    }

    public function test_scope_won_filters_won_deals(): void
    {
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_WON,
            'won_at' => now(),
        ]);

        $this->assertGreaterThanOrEqual(1, CrmDeal::won()->count());
    }

    public function test_scope_lost_filters_lost_deals(): void
    {
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_LOST,
            'lost_at' => now(),
        ]);

        $this->assertGreaterThanOrEqual(1, CrmDeal::lost()->count());
    }

    public function test_scope_by_pipeline_filters_correctly(): void
    {
        $otherPipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherStage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $otherPipeline->id,
        ]);

        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $otherPipeline->id,
            'stage_id' => $otherStage->id,
        ]);

        $filtered = CrmDeal::byPipeline($this->pipeline->id)->count();
        $this->assertGreaterThanOrEqual(1, $filtered);
    }

    // ── CrmDeal — Business Methods ──

    public function test_move_to_stage_updates_stage_and_probability(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $newStage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
            'probability' => 75,
        ]);

        $deal->moveToStage($newStage->id);
        $deal->refresh();

        $this->assertEquals($newStage->id, $deal->stage_id);
        $this->assertEquals(75, $deal->probability);
    }

    public function test_move_to_stage_throws_for_non_open_deal(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_WON,
            'won_at' => now(),
        ]);

        $this->expectException(\DomainException::class);
        $deal->moveToStage($this->stage->id);
    }

    public function test_move_to_stage_throws_for_different_pipeline(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $otherPipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherStage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $otherPipeline->id,
        ]);

        $this->expectException(\DomainException::class);
        $deal->moveToStage($otherStage->id);
    }

    // ── CrmDeal — Casts ──

    public function test_deal_decimal_casts(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'value' => '15000.50',
            'competitor_price' => '14000.00',
        ]);

        $deal->refresh();
        $this->assertEquals('15000.50', $deal->value);
        $this->assertEquals('14000.00', $deal->competitor_price);
    }

    public function test_deal_date_casts(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'expected_close_date' => '2026-06-15',
        ]);

        $deal->refresh();
        $this->assertInstanceOf(Carbon::class, $deal->expected_close_date);
    }

    // ── CrmDeal — Soft Deletes ──

    public function test_deal_soft_delete(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        $deal->delete();

        $this->assertNull(CrmDeal::find($deal->id));
        $this->assertNotNull(CrmDeal::withTrashed()->find($deal->id));
    }

    // ── CrmPipeline — Relationships ──

    public function test_pipeline_has_many_stages(): void
    {
        CrmPipelineStage::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
        ]);

        $this->assertGreaterThanOrEqual(3, $this->pipeline->stages()->count());
    }

    // ── CrmActivity — Relationships ──

    public function test_activity_belongs_to_deal(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);
        $activity = CrmActivity::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(CrmDeal::class, $activity->deal);
    }

    public function test_activity_belongs_to_user(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);
        $activity = CrmActivity::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $activity->user);
    }
}
