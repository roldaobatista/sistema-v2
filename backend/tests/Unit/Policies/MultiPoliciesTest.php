<?php

namespace Tests\Unit\Policies;

use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\AccountPayablePolicy;
use App\Policies\CrmDealPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EquipmentPolicy;
use App\Policies\QuotePolicy;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MultiPoliciesTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $basic;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();

        // Create required permissions
        $permissions = [
            'cadastros.customer.view',
            'cadastros.customer.create',
            'cadastros.customer.update',
            'cadastros.customer.delete',
            'quotes.quote.view',
            'quotes.quote.create',
            'equipments.equipment.view',
            'equipments.equipment.create',
            'finance.payable.view',
            'finance.payable.create',
            'crm.deal.view',
            'crm.deal.create',
        ];
        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Set tenant context before assigning roles/permissions
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        // Get the admin role (already seeded by TestCase) and assign permissions
        $adminRole = \Spatie\Permission\Models\Role::findByName('admin', 'web');
        $adminRole->givePermissionTo($permissions);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->admin->assignRole('admin');

        $this->basic = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->basic->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    // ── CustomerPolicy ──

    public function test_admin_can_view_any_customers(): void
    {
        $this->actingAs($this->admin);
        $policy = new CustomerPolicy;
        $this->assertTrue($policy->viewAny($this->admin));
    }

    public function test_admin_can_create_customer(): void
    {
        $this->actingAs($this->admin);
        $policy = new CustomerPolicy;
        $this->assertTrue($policy->create($this->admin));
    }

    public function test_admin_can_update_customer(): void
    {
        $this->actingAs($this->admin);
        $policy = new CustomerPolicy;
        $this->assertTrue($policy->update($this->admin, $this->customer));
    }

    public function test_admin_can_delete_customer(): void
    {
        $this->actingAs($this->admin);
        $policy = new CustomerPolicy;
        $this->assertTrue($policy->delete($this->admin, $this->customer));
    }

    public function test_cross_tenant_cannot_view_customer(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $otherTenant->id);
        $this->actingAs($otherUser);
        $policy = new CustomerPolicy;

        $this->assertFalse($policy->view($otherUser, $this->customer));
    }

    // ── QuotePolicy ──

    public function test_admin_can_view_any_quotes(): void
    {
        $this->actingAs($this->admin);
        $policy = new QuotePolicy;
        $this->assertTrue($policy->viewAny($this->admin));
    }

    public function test_admin_can_create_quote(): void
    {
        $this->actingAs($this->admin);
        $policy = new QuotePolicy;
        $this->assertTrue($policy->create($this->admin));
    }

    public function test_cross_tenant_cannot_view_quote(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $otherTenant->id);
        $this->actingAs($otherUser);
        $policy = new QuotePolicy;

        $this->assertFalse($policy->view($otherUser, $quote));
    }

    // ── EquipmentPolicy ──

    public function test_admin_can_view_any_equipment(): void
    {
        $this->actingAs($this->admin);
        $policy = new EquipmentPolicy;
        $this->assertTrue($policy->viewAny($this->admin));
    }

    public function test_admin_can_create_equipment(): void
    {
        $this->actingAs($this->admin);
        $policy = new EquipmentPolicy;
        $this->assertTrue($policy->create($this->admin));
    }

    public function test_cross_tenant_cannot_view_equipment(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $otherTenant->id);
        $this->actingAs($otherUser);
        $policy = new EquipmentPolicy;

        $this->assertFalse($policy->view($otherUser, $eq));
    }

    // ── AccountPayablePolicy ──

    public function test_admin_can_view_any_payables(): void
    {
        $this->actingAs($this->admin);
        $policy = new AccountPayablePolicy;
        $this->assertTrue($policy->viewAny($this->admin));
    }

    public function test_admin_can_create_payable(): void
    {
        $this->actingAs($this->admin);
        $policy = new AccountPayablePolicy;
        $this->assertTrue($policy->create($this->admin));
    }

    // ── CrmDealPolicy ──

    public function test_admin_can_view_any_deals(): void
    {
        $this->actingAs($this->admin);
        $policy = new CrmDealPolicy;
        $this->assertTrue($policy->viewAny($this->admin));
    }

    public function test_admin_can_create_deal(): void
    {
        $this->actingAs($this->admin);
        $policy = new CrmDealPolicy;
        $this->assertTrue($policy->create($this->admin));
    }

    public function test_cross_tenant_cannot_view_deal(): void
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

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $otherTenant->id);
        $this->actingAs($otherUser);
        $policy = new CrmDealPolicy;

        $this->assertFalse($policy->view($otherUser, $deal));
    }
}
