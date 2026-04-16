<?php

namespace Tests\Feature\Api\V1\Os;

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

class WorkOrderEquipmentControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private WorkOrder $workOrder;

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

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);
        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_attach_equipment_validates_required_field(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/equipments", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['equipment_id']);
    }

    public function test_attach_equipment_rejects_equipment_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignEquipment = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/equipments", [
            'equipment_id' => $foreignEquipment->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['equipment_id']);
    }

    public function test_attach_equipment_creates_pivot(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/equipments", [
            'equipment_id' => $this->equipment->id,
        ]);

        $response->assertStatus(201);
        $this->assertTrue(
            $this->workOrder->fresh()->equipmentsList()->where('equipment_id', $this->equipment->id)->exists()
        );
    }

    public function test_attach_equipment_rejects_duplicate_attach(): void
    {
        // Primeiro attach
        $this->workOrder->equipmentsList()->attach($this->equipment->id);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/equipments", [
            'equipment_id' => $this->equipment->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_detach_equipment_removes_pivot(): void
    {
        $this->workOrder->equipmentsList()->attach($this->equipment->id);

        $response = $this->deleteJson(
            "/api/v1/work-orders/{$this->workOrder->id}/equipments/{$this->equipment->id}"
        );

        $response->assertStatus(204);
        $this->assertFalse(
            $this->workOrder->fresh()->equipmentsList()->where('equipment_id', $this->equipment->id)->exists()
        );
    }

    public function test_detach_equipment_returns_404_for_cross_tenant_work_order(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $foreignWo = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);
        $foreignEq = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->deleteJson(
            "/api/v1/work-orders/{$foreignWo->id}/equipments/{$foreignEq->id}"
        );

        $response->assertStatus(404);
    }
}
