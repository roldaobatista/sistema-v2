<?php

namespace Tests\Feature\Api\V1\Equipment;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentHistoryControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Equipment $equipment;

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
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_history_returns_equipment_timeline(): void
    {
        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/history");

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_history_rejects_cross_tenant_equipment(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/v1/equipments/{$foreign->id}/history");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_work_orders_returns_only_current_equipment_orders(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'equipment_id' => $this->equipment->id,
        ]);

        $otherEquipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
        $foreign = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'equipment_id' => $otherEquipment->id,
        ]);

        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/work-orders");

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_work_orders_rejects_cross_tenant_equipment(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/v1/equipments/{$foreign->id}/work-orders");

        $this->assertContains($response->status(), [403, 404]);
    }
}
