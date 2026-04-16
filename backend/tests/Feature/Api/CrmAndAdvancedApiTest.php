<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes profundos de CRM, Equipment avançado, e Commission APIs.
 */
class CrmAndAdvancedApiTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    // ═══ CRM Deals API ═══

    public function test_crm_deals_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/crm/deals');
        $response->assertOk();
    }

    public function test_crm_deal_store(): void
    {
        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/crm/deals', [
            'customer_id' => $this->customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'title' => 'Calibração 2026',
            'value' => '25000.00',
            'probability' => 50,
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));
    }

    public function test_crm_deal_show(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/crm/deals/{$deal->id}");
        $response->assertOk();
    }

    public function test_crm_deal_update(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/crm/deals/{$deal->id}", [
            'title' => 'Deal Atualizado',
        ]);
        $response->assertOk();
    }

    public function test_crm_deals_filter_by_status(): void
    {
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/crm/deals?status=open');
        $response->assertOk();
    }

    // ═══ CRM Pipelines API ═══

    public function test_crm_pipelines_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/crm/pipelines');
        $response->assertOk();
    }

    // ═══ Equipment Advanced ═══

    public function test_equipment_calibration_list(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/equipments/{$eq->id}/calibrations");
        $response->assertOk();
    }

    public function test_equipment_filter_by_customer(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/equipments?customer_id={$this->customer->id}");
        $response->assertOk();
    }

    public function test_equipment_filter_critical(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_critical' => true,
        ]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/equipments?is_critical=1');
        $response->assertOk();
    }

    // ═══ Commission API ═══

    public function test_commission_rules_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/commissions/rules');
        $response->assertOk();
    }

    public function test_commission_events_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/commissions/events');
        $response->assertOk();
    }

    // ═══ Dashboard ═══

    public function test_dashboard_loads(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');
        $response->assertOk();
    }

    public function test_dashboard_kpis_contains_data(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');
        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    // ═══ Settings API ═══

    public function test_settings_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/settings');
        $response->assertOk();
    }

    // ═══ Branches API ═══

    public function test_branches_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/branches');
        $response->assertOk();
    }

    // ═══ Users API ═══

    public function test_users_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/users');
        $response->assertOk();
    }

    public function test_roles_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/roles');
        $response->assertOk();
    }

    // ═══ Cross-tenant isolation ═══

    public function test_crm_deal_not_visible_cross_tenant(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);
        $otherUser->assignRole('admin');

        app()->instance('current_tenant_id', $this->tenant->id);

        $response = $this->actingAs($otherUser)->getJson("/api/v1/crm/deals/{$deal->id}");
        $response->assertNotFound();
    }

    // ═══ Unauthenticated ═══

    public function test_crm_deals_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/crm/deals');
        $response->assertUnauthorized();
    }

    public function test_commissions_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/commissions/events');
        $response->assertUnauthorized();
    }
}
