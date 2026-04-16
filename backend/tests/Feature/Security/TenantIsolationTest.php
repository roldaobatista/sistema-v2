<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\CheckPermission;
use App\Models\AccountPayable;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        // Setup user A with tenant A
        app()->instance('current_tenant_id', $this->tenantA->id);
        setPermissionsTeamId($this->tenantA->id);
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
        ]);
        $this->userA->tenants()->attach($this->tenantA->id, ['is_default' => true]);
        $this->userA->assignRole('admin');

        // Setup user B with tenant B
        app()->instance('current_tenant_id', $this->tenantB->id);
        setPermissionsTeamId($this->tenantB->id);
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
        ]);
        $this->userB->tenants()->attach($this->tenantB->id, ['is_default' => true]);
        $this->userB->assignRole('admin');

        $this->customerA = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);
    }

    // ── WorkOrder ──

    public function test_user_b_cannot_see_work_orders_of_tenant_a(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        $response = $this->actingAs($this->userB)->getJson('/api/v1/work-orders');
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_user_b_cannot_show_work_order_of_tenant_a(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->getJson("/api/v1/work-orders/{$wo->id}");
        $response->assertNotFound();
    }

    public function test_user_b_cannot_update_work_order_of_tenant_a(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->putJson("/api/v1/work-orders/{$wo->id}", [
            'title' => 'Hacked',
        ]);
        $response->assertNotFound();
    }

    public function test_user_b_cannot_delete_work_order_of_tenant_a(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->deleteJson("/api/v1/work-orders/{$wo->id}");
        $response->assertNotFound();
    }

    // ── Customer ──

    public function test_user_b_cannot_see_customers_of_tenant_a(): void
    {
        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->getJson('/api/v1/customers');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($this->customerA->id, $ids);
    }

    public function test_user_b_cannot_show_customer_of_tenant_a(): void
    {
        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->getJson("/api/v1/customers/{$this->customerA->id}");
        $response->assertNotFound();
    }

    // ── Equipment ──

    public function test_user_b_cannot_see_equipment_of_tenant_a(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->getJson('/api/v1/equipments');
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ── Quote ──

    public function test_user_b_cannot_see_quotes_of_tenant_a(): void
    {
        Quote::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->getJson('/api/v1/quotes');
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ── AccountPayable ──

    public function test_user_b_cannot_see_payables_of_tenant_a(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->userA->id,
        ]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->getJson('/api/v1/account-payables');
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ── CrmDeal ──

    public function test_user_b_cannot_see_deals_of_tenant_a(): void
    {
        $pipelineA = CrmPipeline::factory()->create(['tenant_id' => $this->tenantA->id]);
        $stageA = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'pipeline_id' => $pipelineA->id,
        ]);

        CrmDeal::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'pipeline_id' => $pipelineA->id,
            'stage_id' => $stageA->id,
        ]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->getJson('/api/v1/crm/deals');
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ── Products ──

    public function test_user_b_cannot_see_products_of_tenant_a(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenantA->id]);

        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->getJson('/api/v1/products');
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ── Data cannot leak between create requests ──

    public function test_user_b_cannot_create_wo_with_tenant_a_customer(): void
    {
        app()->instance('current_tenant_id', $this->tenantB->id);
        $response = $this->actingAs($this->userB)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customerA->id,
            'title' => 'Cross-tenant attempt',
        ]);

        $response->assertStatus(422);
    }
}
