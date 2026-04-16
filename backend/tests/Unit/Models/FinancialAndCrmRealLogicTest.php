<?php

namespace Tests\Unit\Models;

use App\Enums\FinancialStatus;
use App\Models\AccountReceivable;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos: AccountReceivable recalculateStatus() e CrmDeal moveToStage().
 */
class FinancialAndCrmRealLogicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);
    }

    // ═══ AccountReceivable.recalculateStatus() ═══

    public function test_ar_fully_paid_becomes_paid(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '2000.00',
            'amount_paid' => '2000.00',
            'due_date' => now()->addDays(10),
            'status' => FinancialStatus::PENDING,
        ]);
        $ar->recalculateStatus();
        $ar->refresh();
        $this->assertEquals(FinancialStatus::PAID, $ar->status);
        $this->assertNotNull($ar->paid_at);
    }

    public function test_ar_partial_payment(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '1000.00',
            'amount_paid' => '400.00',
            'due_date' => now()->addDays(30),
            'status' => FinancialStatus::PENDING,
        ]);
        $ar->recalculateStatus();
        $ar->refresh();
        $this->assertEquals(FinancialStatus::PARTIAL, $ar->status);
    }

    public function test_ar_past_due_becomes_overdue(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'due_date' => now()->subDays(10),
            'status' => FinancialStatus::PENDING,
        ]);
        $ar->recalculateStatus();
        $ar->refresh();
        $this->assertEquals(FinancialStatus::OVERDUE, $ar->status);
    }

    public function test_ar_cancelled_not_recalculated(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '500.00',
            'amount_paid' => '500.00',
            'status' => FinancialStatus::CANCELLED,
        ]);
        $ar->recalculateStatus();
        $ar->refresh();
        $this->assertEquals(FinancialStatus::CANCELLED, $ar->status);
    }

    public function test_ar_renegotiated_not_recalculated(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '500.00',
            'amount_paid' => '0.00',
            'due_date' => now()->subDays(30),
            'status' => FinancialStatus::RENEGOTIATED,
        ]);
        $ar->recalculateStatus();
        $ar->refresh();
        $this->assertEquals(FinancialStatus::RENEGOTIATED, $ar->status);
    }

    public function test_ar_overpaid_becomes_paid(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '500.00',
            'amount_paid' => '600.00',
            'due_date' => now()->addDays(5),
            'status' => FinancialStatus::PENDING,
        ]);
        $ar->recalculateStatus();
        $ar->refresh();
        $this->assertEquals(FinancialStatus::PAID, $ar->status);
    }

    public function test_ar_zero_payment_future_due_stays_pending(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'due_date' => now()->addDays(30),
            'status' => FinancialStatus::PENDING,
        ]);
        $ar->recalculateStatus();
        $ar->refresh();
        $this->assertEquals(FinancialStatus::PENDING, $ar->status);
    }

    // ── AR Constants ──

    public function test_ar_payment_methods(): void
    {
        $this->assertArrayHasKey('pix', AccountReceivable::PAYMENT_METHODS);
        $this->assertArrayHasKey('boleto', AccountReceivable::PAYMENT_METHODS);
        $this->assertArrayHasKey('dinheiro', AccountReceivable::PAYMENT_METHODS);
    }

    public function test_ar_statuses_method(): void
    {
        $statuses = AccountReceivable::statuses();
        $this->assertArrayHasKey('pending', $statuses);
        $this->assertArrayHasKey('paid', $statuses);
    }

    // ── AR Relationships ──

    public function test_ar_belongs_to_customer(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(Customer::class, $ar->customer);
    }

    public function test_ar_belongs_to_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $wo->id,
        ]);
        $this->assertInstanceOf(WorkOrder::class, $ar->workOrder);
    }

    // ═══ CrmDeal ═══

    // ── Scopes ──

    public function test_crm_deal_scope_open(): void
    {
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);
        $this->assertGreaterThanOrEqual(1, CrmDeal::open()->count());
    }

    public function test_crm_deal_scope_won(): void
    {
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => CrmDeal::STATUS_WON,
        ]);
        $this->assertGreaterThanOrEqual(1, CrmDeal::won()->count());
    }

    public function test_crm_deal_scope_lost(): void
    {
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => CrmDeal::STATUS_LOST,
        ]);
        $this->assertGreaterThanOrEqual(1, CrmDeal::lost()->count());
    }

    // ── moveToStage() ──

    public function test_move_to_stage_throws_when_not_open(): void
    {
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
            'status' => CrmDeal::STATUS_WON,
        ]);

        $this->expectException(\DomainException::class);
        $deal->moveToStage($stage->id);
    }

    public function test_move_to_stage_throws_cross_pipeline(): void
    {
        $pipeline1 = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $pipeline2 = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage1 = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline1->id,
        ]);
        $stage2 = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline2->id,
        ]);
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline1->id,
            'stage_id' => $stage1->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $this->expectException(\DomainException::class);
        $deal->moveToStage($stage2->id);
    }

    public function test_move_to_stage_success(): void
    {
        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage1 = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'probability' => 25,
        ]);
        $stage2 = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'probability' => 75,
        ]);
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage1->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $deal->moveToStage($stage2->id);
        $deal->refresh();

        $this->assertEquals($stage2->id, $deal->stage_id);
        $this->assertEquals(75, $deal->probability);
    }

    // ── Constants ──

    public function test_crm_deal_sources(): void
    {
        $this->assertArrayHasKey('calibracao_vencendo', CrmDeal::SOURCES);
        $this->assertArrayHasKey('indicacao', CrmDeal::SOURCES);
        $this->assertArrayHasKey('prospeccao', CrmDeal::SOURCES);
    }

    public function test_crm_deal_statuses(): void
    {
        $this->assertArrayHasKey(CrmDeal::STATUS_OPEN, CrmDeal::STATUSES);
        $this->assertArrayHasKey(CrmDeal::STATUS_WON, CrmDeal::STATUSES);
        $this->assertArrayHasKey(CrmDeal::STATUS_LOST, CrmDeal::STATUSES);
    }

    // ── Casts ──

    public function test_crm_deal_value_decimal(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'value' => '25000.00',
        ]);
        $this->assertEquals('25000.00', $deal->value);
    }

    public function test_crm_deal_probability_integer(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'probability' => 75,
        ]);
        $this->assertIsInt($deal->probability);
    }

    // ── Soft Delete ──

    public function test_crm_deal_soft_deletes(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $deal->delete();
        $this->assertSoftDeleted($deal);
    }
}
