<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Lookups\LeadSource;
use App\Models\Lookups\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderLookupCompatibilityTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_store_accepts_service_type_lookup_slug(): void
    {
        ServiceType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manutencao Corretiva Especial',
            'slug' => 'manutencao-corretiva-especial',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => 'OS criada com tipo lookup',
            'service_type' => 'manutencao-corretiva-especial',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('work_orders', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'manutencao-corretiva-especial',
        ]);
    }

    public function test_store_keeps_legacy_service_type_compatible(): void
    {
        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => 'OS com tipo legado',
            'service_type' => 'preventiva',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('work_orders', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'preventiva',
        ]);
    }

    public function test_update_accepts_lookup_service_type_and_lead_source(): void
    {
        ServiceType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Diagnostico Express',
            'slug' => 'diagnostico-express',
            'is_active' => true,
        ]);

        LeadSource::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Google Ads',
            'slug' => 'google-ads',
            'is_active' => true,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $this->putJson("/api/v1/work-orders/{$workOrder->id}", [
            'service_type' => 'diagnostico-express',
            'lead_source' => 'google-ads',
        ])->assertOk();

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'service_type' => 'diagnostico-express',
            'lead_source' => 'google-ads',
        ]);
    }
}
