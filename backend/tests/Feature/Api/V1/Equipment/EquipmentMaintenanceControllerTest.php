<?php

namespace Tests\Feature\Api\V1\Equipment;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentMaintenanceControllerTest extends TestCase
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

    private function createMaintenance(?int $tenantId = null, ?int $equipmentId = null): EquipmentMaintenance
    {
        return EquipmentMaintenance::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'equipment_id' => $equipmentId ?? $this->equipment->id,
            'type' => 'preventiva',
            'description' => 'Manutenção preventiva mensal',
            'cost' => 250.00,
            'downtime_hours' => 2.5,
            'performed_by' => $this->user->id,
        ]);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $mine = $this->createMaintenance();

        $otherTenant = Tenant::factory()->create();
        $otherEquipment = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createMaintenance($otherTenant->id, $otherEquipment->id);

        $response = $this->getJson('/api/v1/equipment-maintenances');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/equipment-maintenances', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_type_enum(): void
    {
        $response = $this->postJson('/api/v1/equipment-maintenances', [
            'equipment_id' => $this->equipment->id,
            'type' => 'tipo-invalido',
            'description' => 'Descrição teste',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_equipment_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/equipment-maintenances', [
            'equipment_id' => $foreign->id,
            'type' => 'preventiva',
            'description' => 'Teste',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_creates_maintenance(): void
    {
        $response = $this->postJson('/api/v1/equipment-maintenances', [
            'equipment_id' => $this->equipment->id,
            'type' => 'corretiva',
            'description' => 'Troca de peça',
            'cost' => 500.00,
            'downtime_hours' => 4,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('equipment_maintenances', [
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'corretiva',
        ]);
    }

    public function test_show_returns_maintenance(): void
    {
        $maint = $this->createMaintenance();

        $response = $this->getJson("/api/v1/equipment-maintenances/{$maint->id}");

        $response->assertOk();
    }
}
