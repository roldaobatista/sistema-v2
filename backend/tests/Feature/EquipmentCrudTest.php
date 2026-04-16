<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentCrudTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_equipment_crud_and_tenant_isolation(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $create = $this->postJson('/api/v1/equipments', [
            'customer_id' => $customer->id,
            'type' => 'Balanca',
            'category' => 'balanca_plataforma',
            'serial_number' => 'EQ-SN-0001',
            'status' => Equipment::STATUS_ACTIVE,
            'tenant_id' => $this->otherTenant->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.serial_number', 'EQ-SN-0001')
            ->assertJsonPath('data.tenant_id', $this->tenant->id);

        $equipmentId = (int) $create->json('data.id');

        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $this->otherTenant->id,
        ]);

        Equipment::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'serial_number' => 'EQ-SN-OTHER',
        ]);

        $this->getJson('/api/v1/equipments')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/equipments/{$equipmentId}")
            ->assertOk()
            ->assertJsonPath('data.id', $equipmentId);

        $foreignEquipment = Equipment::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->otherTenant->id)
            ->firstOrFail();

        $this->getJson("/api/v1/equipments/{$foreignEquipment->id}")
            ->assertStatus(404);

        $this->putJson("/api/v1/equipments/{$equipmentId}", [
            'brand' => 'Marca Atualizada',
            'status' => Equipment::STATUS_IN_MAINTENANCE,
        ])
            ->assertOk()
            ->assertJsonPath('data.brand', 'Marca Atualizada');

        $this->assertDatabaseHas('equipments', [
            'id' => $equipmentId,
            'brand' => 'Marca Atualizada',
            'status' => Equipment::STATUS_IN_MAINTENANCE,
        ]);
    }
}
