<?php

namespace Tests\Unit\Models;

use App\Http\Middleware\CheckPermission;
use App\Models\ChartOfAccount;
use App\Models\Contract;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Department;
use App\Models\InventoryItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes profundos: CRM lifecycle, ChartOfAccount, Department,
 * Contract, InventoryItem.
 */
class CrmAndOrganizationModelsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
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

    // ═══ CRM Activity ═══

    public function test_crm_activity_create(): void
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
        ]);
        $activity = CrmActivity::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('crm_activities', ['id' => $activity->id]);
    }

    public function test_crm_activity_belongs_to_deal(): void
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
        ]);
        $activity = CrmActivity::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertInstanceOf(CrmDeal::class, $activity->deal);
    }

    // ═══ CRM Deal lifecycle ═══

    public function test_deal_with_value(): void
    {
        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'probability' => 75,
        ]);
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'value' => '50000.00',
        ]);
        $this->assertEquals('50000.00', $deal->value);
    }

    public function test_deal_weighted_value(): void
    {
        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'probability' => 50,
        ]);
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'value' => '10000.00',
        ]);
        $weighted = bcmul($deal->value, bcdiv((string) $stage->probability, '100', 2), 2);
        $this->assertEquals('5000.00', $weighted);
    }

    // ═══ ChartOfAccount ═══

    public function test_chart_of_account_create(): void
    {
        $coa = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $coa->id]);
    }

    public function test_chart_of_account_belongs_to_tenant(): void
    {
        $coa = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $coa->tenant_id);
    }

    // ═══ Department ═══

    public function test_department_create(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('departments', ['id' => $dept->id]);
    }

    public function test_department_belongs_to_tenant(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $dept->tenant_id);
    }

    // ═══ Contract ═══

    public function test_contract_create(): void
    {
        $c = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertDatabaseHas('contracts', ['id' => $c->id]);
    }

    public function test_contract_belongs_to_customer(): void
    {
        $c = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(Customer::class, $c->customer);
    }

    public function test_contract_soft_deletes(): void
    {
        $c = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $c->delete();
        $this->assertSoftDeleted($c);
    }

    // ═══ InventoryItem ═══

    public function test_inventory_item_create(): void
    {
        $item = InventoryItem::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
    }

    public function test_inventory_item_stock_level(): void
    {
        $item = InventoryItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'expected_quantity' => 50,
            'counted_quantity' => 10,
        ]);
        $this->assertGreaterThan($item->counted_quantity, $item->expected_quantity);
    }
}
