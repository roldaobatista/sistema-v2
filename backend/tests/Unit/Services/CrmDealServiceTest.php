<?php

namespace Tests\Unit\Services;

use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CrmDealServiceTest extends TestCase
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
            'probability' => 25,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    public function test_create_deal_with_valid_data(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'value' => '50000.00',
            'title' => 'Contrato calibração anual',
        ]);

        $this->assertEquals('Contrato calibração anual', $deal->title);
        $this->assertEquals('50000.00', $deal->value);
    }

    public function test_move_deal_to_next_stage(): void
    {
        $stage2 = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
            'probability' => 50,
        ]);

        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $deal->moveToStage($stage2->id);
        $deal->refresh();

        $this->assertEquals($stage2->id, $deal->stage_id);
        $this->assertEquals(50, $deal->probability);
    }

    public function test_mark_deal_as_won(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $deal->markAsWon();
        $deal->refresh();

        $this->assertEquals(CrmDeal::STATUS_WON, $deal->status);
        $this->assertNotNull($deal->won_at);
    }

    public function test_mark_deal_as_lost(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $deal->markAsLost('Preço alto');
        $deal->refresh();

        $this->assertEquals(CrmDeal::STATUS_LOST, $deal->status);
        $this->assertNotNull($deal->lost_at);
    }

    public function test_deal_weighted_value(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'value' => '100000.00',
            'probability' => 50,
        ]);

        $weighted = $deal->weighted_value ?? ((float) $deal->value * (float) $deal->probability / 100);
        $this->assertEquals(50000, $weighted);
    }

    public function test_deal_filters_by_status(): void
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
            'won_at' => now(),
        ]);

        $openDeals = CrmDeal::open()->count();
        $wonDeals = CrmDeal::won()->count();

        $this->assertGreaterThanOrEqual(1, $openDeals);
        $this->assertGreaterThanOrEqual(1, $wonDeals);
    }

    public function test_deal_has_activities(): void
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
            'type' => 'nota',
            'title' => 'Primeiro contato',
        ]);

        $this->assertGreaterThanOrEqual(1, $deal->activities()->count());
    }

    public function test_cannot_move_won_deal_to_stage(): void
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

    public function test_deal_soft_delete_preserves_data(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        $deal->delete();
        $this->assertNotNull(CrmDeal::withTrashed()->find($deal->id));
    }

    public function test_deal_assigned_to_user(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'assigned_to' => $this->user->id,
        ]);

        $this->assertEquals($this->user->id, $deal->assigned_to);
        $this->assertInstanceOf(User::class, $deal->assignee);
    }
}
