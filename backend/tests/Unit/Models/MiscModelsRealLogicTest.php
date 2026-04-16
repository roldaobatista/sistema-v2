<?php

namespace Tests\Unit\Models;

use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\NumberingSequence;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos reais: ServiceCall, Branch, NumberingSequence,
 * BankAccount, CrmPipeline, CrmPipelineStage.
 */
class MiscModelsRealLogicTest extends TestCase
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

    // ═══ ServiceCall ═══

    public function test_service_call_create(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertDatabaseHas('service_calls', ['id' => $sc->id]);
    }

    public function test_service_call_belongs_to_customer(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(Customer::class, $sc->customer);
    }

    public function test_service_call_soft_deletes(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $sc->delete();
        $this->assertSoftDeleted($sc);
    }

    // ═══ Branch ═══

    public function test_branch_create(): void
    {
        $b = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('branches', ['id' => $b->id]);
    }

    public function test_branch_belongs_to_tenant(): void
    {
        $b = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertInstanceOf(Tenant::class, $b->tenant);
    }

    // ═══ NumberingSequence ═══

    public function test_numbering_sequence_create(): void
    {
        $ns = NumberingSequence::factory()->create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'equipment',
            'prefix' => 'EQ-',
            'next_number' => 1,
        ]);
        $this->assertDatabaseHas('numbering_sequences', ['id' => $ns->id]);
    }

    public function test_numbering_sequence_belongs_to_tenant(): void
    {
        $ns = NumberingSequence::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $ns->tenant_id);
    }

    // ═══ CrmPipeline ═══

    public function test_pipeline_create(): void
    {
        $p = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('crm_pipelines', ['id' => $p->id]);
    }

    public function test_pipeline_has_stages(): void
    {
        $p = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        CrmPipelineStage::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $p->id,
        ]);
        $this->assertEquals(3, $p->stages()->count());
    }

    // ═══ CrmPipelineStage ═══

    public function test_stage_create(): void
    {
        $p = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $s = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $p->id,
        ]);
        $this->assertDatabaseHas('crm_pipeline_stages', ['id' => $s->id]);
    }

    public function test_stage_belongs_to_pipeline(): void
    {
        $p = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $s = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $p->id,
        ]);
        $this->assertInstanceOf(CrmPipeline::class, $s->pipeline);
    }

    public function test_stage_probability(): void
    {
        $p = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $s = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $p->id,
            'probability' => 50,
        ]);
        $this->assertEquals(50, $s->probability);
    }

    // ═══ BankAccount (if factory exists) ═══

    public function test_bank_account_create(): void
    {
        $ba = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('bank_accounts', ['id' => $ba->id]);
    }

    public function test_bank_account_initial_balance_cast(): void
    {
        $ba = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'initial_balance' => '5000.00',
        ]);
        $this->assertEquals('5000.00', $ba->initial_balance);
    }
}
