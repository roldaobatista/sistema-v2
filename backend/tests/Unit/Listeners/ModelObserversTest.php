<?php

namespace Tests\Unit\Listeners;

use App\Models\AccountPayable;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ModelObserversTest extends TestCase
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
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── Customer Observer ──

    public function test_creating_customer_sets_tenant(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $customer->tenant_id);
    }

    public function test_deleting_customer_soft_deletes(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer->delete();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    // ── Equipment Observer ──

    public function test_creating_equipment_auto_sets_tenant(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertEquals($this->tenant->id, $eq->tenant_id);
    }

    public function test_deleting_equipment_soft_deletes(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $eq->delete();
        $this->assertSoftDeleted('equipments', ['id' => $eq->id]);
    }

    // ── Quote Observer ──

    public function test_creating_quote_sets_tenant(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertEquals($this->tenant->id, $quote->tenant_id);
    }

    public function test_deleting_quote_soft_deletes(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $quote->delete();
        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    // ── CrmDeal Observer ──

    public function test_creating_deal_sets_tenant(): void
    {
        Event::fake();
        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
        ]);

        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
        ]);

        $this->assertEquals($this->tenant->id, $deal->tenant_id);
    }

    // ── AccountPayable Observer ──

    public function test_creating_payable_sets_tenant(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        $this->assertEquals($this->tenant->id, $ap->tenant_id);
    }

    public function test_deleting_payable_soft_deletes(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        $ap->delete();
        $this->assertSoftDeleted('accounts_payable', ['id' => $ap->id]);
    }
}
