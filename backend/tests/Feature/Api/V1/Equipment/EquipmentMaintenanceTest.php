<?php

namespace Tests\Feature\Api\V1\Equipment;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentMaintenanceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_index_returns_paginated_maintenances(): void
    {
        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'preventiva',
            'description' => 'Manutenção preventiva teste',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/equipment-maintenances');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_index_filters_by_equipment_id(): void
    {
        $equipment2 = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'preventiva',
            'description' => 'From equipment 1',
            'performed_by' => $this->user->id,
        ]);
        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment2->id,
            'type' => 'corretiva',
            'description' => 'From equipment 2',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/equipment-maintenances?equipment_id={$this->equipment->id}");

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        $eqIds = collect($items)->pluck('equipment_id')->unique()->values()->all();
        $this->assertEquals([$this->equipment->id], $eqIds);
    }

    public function test_index_filters_by_type(): void
    {
        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'preventiva',
            'description' => 'Prev',
            'performed_by' => $this->user->id,
        ]);
        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'corretiva',
            'description' => 'Corr',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/equipment-maintenances?type=preventiva');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals('preventiva', $item['type']);
        }
    }

    public function test_store_creates_maintenance_successfully(): void
    {
        $payload = [
            'equipment_id' => $this->equipment->id,
            'type' => 'corretiva',
            'description' => 'Troca de componente queimado',
            'cost' => 150.50,
            'downtime_hours' => 3.5,
        ];

        $response = $this->postJson('/api/v1/equipment-maintenances', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('equipment_maintenances', [
            'equipment_id' => $this->equipment->id,
            'type' => 'corretiva',
            'tenant_id' => $this->tenant->id,
            'performed_by' => $this->user->id,
        ]);
    }

    public function test_store_fails_with_invalid_type(): void
    {
        $payload = [
            'equipment_id' => $this->equipment->id,
            'type' => 'invalido',
            'description' => 'Test',
        ];

        $response = $this->postJson('/api/v1/equipment-maintenances', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
    }

    public function test_store_fails_without_required_fields(): void
    {
        $response = $this->postJson('/api/v1/equipment-maintenances', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['equipment_id', 'type', 'description']);
    }

    public function test_store_rejects_equipment_from_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherEquipment = Equipment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $otherTenant->id])->id,
        ]);

        $payload = [
            'equipment_id' => $otherEquipment->id,
            'type' => 'preventiva',
            'description' => 'Cross-tenant attempt',
        ];

        $response = $this->postJson('/api/v1/equipment-maintenances', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('equipment_id');
    }

    public function test_show_returns_single_maintenance(): void
    {
        $maintenance = EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'ajuste',
            'description' => 'Ajuste de zero',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/equipment-maintenances/{$maintenance->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($maintenance->id, $data['id']);
        $this->assertEquals('ajuste', $data['type']);
    }

    public function test_show_rejects_maintenance_from_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherEquipment = Equipment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $otherMaint = EquipmentMaintenance::create([
            'tenant_id' => $otherTenant->id,
            'equipment_id' => $otherEquipment->id,
            'type' => 'preventiva',
            'description' => 'Other tenant',
            'performed_by' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/equipment-maintenances/{$otherMaint->id}");

        // BelongsToTenant global scope returns 404 (not found in current tenant)
        // or controller tenant check returns 403
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_update_modifies_maintenance(): void
    {
        $maintenance = EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'preventiva',
            'description' => 'Original',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/equipment-maintenances/{$maintenance->id}", [
            'description' => 'Atualizada',
            'cost' => 200,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('equipment_maintenances', [
            'id' => $maintenance->id,
            'description' => 'Atualizada',
        ]);
    }

    public function test_destroy_deletes_maintenance(): void
    {
        $maintenance = EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'limpeza',
            'description' => 'To be deleted',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/equipment-maintenances/{$maintenance->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('equipment_maintenances', ['id' => $maintenance->id]);
    }

    public function test_store_with_all_optional_fields(): void
    {
        $payload = [
            'equipment_id' => $this->equipment->id,
            'type' => 'preventiva',
            'description' => 'Manutenção completa',
            'parts_replaced' => 'Sensor de peso, célula de carga',
            'cost' => 580.75,
            'downtime_hours' => 8.0,
            'next_maintenance_at' => '2027-06-15',
        ];

        $response = $this->postJson('/api/v1/equipment-maintenances', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('equipment_maintenances', [
            'equipment_id' => $this->equipment->id,
            'parts_replaced' => 'Sensor de peso, célula de carga',
        ]);
    }

    public function test_index_search_by_description(): void
    {
        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'preventiva',
            'description' => 'Troca de bateria do sensor',
            'performed_by' => $this->user->id,
        ]);
        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'corretiva',
            'description' => 'Calibração emergencial',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/equipment-maintenances?search=bateria');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        $this->assertCount(1, $items);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/equipment-maintenances');

        $response->assertUnauthorized();
    }
}
