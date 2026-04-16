<?php

namespace Tests\Feature\Api\V1\Equipment;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\EquipmentMaintenance;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentHistoryTest extends TestCase
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

    public function test_returns_empty_history_for_equipment_without_events(): void
    {
        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/history");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    public function test_returns_calibrations_in_history(): void
    {
        EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'calibration_date' => now()->subDays(10),
            'certificate_number' => 'CAL-001',
            'result' => 'approved',
            'performed_by' => $this->user->id,
        ]);
        EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'calibration_date' => now()->subDays(20),
            'certificate_number' => 'CAL-002',
            'result' => 'rejected',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/history");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));

        $calibrations = collect($data)->where('type', 'calibration');
        $this->assertCount(2, $calibrations);
        $this->assertStringContainsString('CAL-001', $calibrations->first()['title']);
    }

    public function test_returns_maintenances_in_history(): void
    {
        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'preventiva',
            'description' => 'Manutenção preventiva anual',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/history");

        $response->assertOk();
        $data = $response->json('data');
        $maintenances = collect($data)->where('type', 'maintenance');
        $this->assertCount(1, $maintenances);
        $this->assertEquals('Manutenção preventiva anual', $maintenances->first()['description']);
    }

    public function test_returns_work_orders_in_history(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'equipment_id' => $this->equipment->id,
            'number' => 'OS-000321',
            'os_number' => '321',
            'status' => WorkOrder::STATUS_COMPLETED,
            'description' => 'Troca de celula de carga',
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/history");

        $response->assertOk();
        $workOrders = collect($response->json('data'))->where('type', 'work_order');
        $this->assertCount(1, $workOrders);
        $this->assertStringContainsString('321', $workOrders->first()['title']);
        $this->assertEquals('completed', $workOrders->first()['status']);
    }

    public function test_history_is_sorted_by_date_descending(): void
    {
        EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'calibration_date' => now()->subDays(30),
            'certificate_number' => 'OLD-CAL',
            'result' => 'approved',
            'performed_by' => $this->user->id,
        ]);

        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'corretiva',
            'description' => 'Recent maintenance',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/history");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));
        // Most recent first
        $dates = collect($data)->pluck('date');
        $sorted = $dates->sortByDesc(fn ($d) => $d)->values();
        $this->assertEquals($sorted->all(), $dates->values()->all());
    }

    public function test_history_rejects_equipment_from_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        // Must create with other tenant's scope active to bypass BelongsToTenant
        $otherEquipment = Equipment::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'code' => 'EQ-OTHER-001',
            'type' => 'balanca',
            'brand' => 'Test',
            'model' => 'Test',
            'serial_number' => 'SN-OTHER-001',
            'status' => 'active',
        ]);

        // BelongsToTenant global scope will make route model binding fail with 404
        $response = $this->getJson("/api/v1/equipments/{$otherEquipment->id}/history");

        // Either 403 (tenant check in controller) or 404 (global scope filtering)
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_history_includes_all_event_types(): void
    {
        EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'calibration_date' => now()->subDays(5),
            'certificate_number' => 'CAL-MIX',
            'result' => 'approved',
            'performed_by' => $this->user->id,
        ]);

        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'ajuste',
            'description' => 'Ajuste feito',
            'performed_by' => $this->user->id,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'equipment_id' => $this->equipment->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'description' => 'OS de teste',
            'completed_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/history");

        $response->assertOk();
        $data = $response->json('data');
        $types = collect($data)->pluck('type')->unique()->values()->all();
        $this->assertContains('calibration', $types);
        $this->assertContains('maintenance', $types);
        $this->assertContains('work_order', $types);
    }

    public function test_history_returns_correct_structure_per_calibration(): void
    {
        EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'calibration_date' => '2026-01-15',
            'certificate_number' => 'CERT-STRUCTURE',
            'result' => 'approved',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/history");
        $response->assertOk();
        $cal = collect($response->json('data'))->where('type', 'calibration')->first();
        $this->assertArrayHasKey('id', $cal);
        $this->assertArrayHasKey('type', $cal);
        $this->assertArrayHasKey('title', $cal);
        $this->assertArrayHasKey('result', $cal);
        $this->assertArrayHasKey('date', $cal);
    }

    public function test_history_returns_correct_structure_per_maintenance(): void
    {
        EquipmentMaintenance::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'type' => 'limpeza',
            'description' => 'Limpeza geral',
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/history");
        $response->assertOk();
        $maint = collect($response->json('data'))->where('type', 'maintenance')->first();
        $this->assertArrayHasKey('id', $maint);
        $this->assertArrayHasKey('type', $maint);
        $this->assertArrayHasKey('title', $maint);
        $this->assertArrayHasKey('description', $maint);
        $this->assertArrayHasKey('date', $maint);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/history");

        $response->assertUnauthorized();
    }
}
