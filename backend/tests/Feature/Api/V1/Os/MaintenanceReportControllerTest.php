<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\MaintenanceReport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceReportControllerTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

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
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
    }

    // ─── Store ──────────────────────────────────────────────────

    public function test_store_creates_maintenance_report(): void
    {
        $payload = [
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'defect_found' => 'Célula de carga com drift excessivo',
            'probable_cause' => 'Umidade no conector',
            'corrective_action' => 'Troca do conector e recalibração',
            'parts_replaced' => [
                ['name' => 'Conector IP68', 'part_number' => 'CN-68-4P', 'quantity' => 1],
            ],
            'seal_status' => 'replaced',
            'new_seal_number' => 'SEAL-2026-0042',
            'condition_before' => 'defective',
            'condition_after' => 'requires_calibration',
            'requires_calibration_after' => true,
            'requires_ipem_verification' => false,
        ];

        $response = $this->postJson('/api/v1/maintenance-reports', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => [
                'id', 'tenant_id', 'work_order_id', 'equipment_id',
                'defect_found', 'probable_cause', 'corrective_action',
                'parts_replaced', 'seal_status', 'new_seal_number',
                'condition_before', 'condition_after',
                'requires_calibration_after', 'requires_ipem_verification',
                'performer',
            ]]);

        $this->assertDatabaseHas('maintenance_reports', [
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'defect_found' => 'Célula de carga com drift excessivo',
            'seal_status' => 'replaced',
            'new_seal_number' => 'SEAL-2026-0042',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_returns_422_for_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/maintenance-reports', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['work_order_id', 'equipment_id', 'defect_found', 'condition_before', 'condition_after']);
    }

    public function test_store_returns_422_for_invalid_seal_without_number(): void
    {
        $payload = [
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'defect_found' => 'Problema qualquer',
            'condition_before' => 'defective',
            'condition_after' => 'functional',
            'seal_status' => 'replaced',
            // missing new_seal_number
        ];

        $response = $this->postJson('/api/v1/maintenance-reports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_seal_number']);
    }

    // ─── Index ──────────────────────────────────────────────────

    public function test_index_returns_paginated_reports(): void
    {
        MaintenanceReport::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/maintenance-reports');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_by_work_order(): void
    {
        MaintenanceReport::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'performed_by' => $this->user->id,
        ]);

        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        MaintenanceReport::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $otherWo->id,
            'equipment_id' => $this->equipment->id,
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/maintenance-reports?work_order_id='.$this->workOrder->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // ─── Show ───────────────────────────────────────────────────

    public function test_show_returns_report_with_relations(): void
    {
        $report = MaintenanceReport::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'performed_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/maintenance-reports/{$report->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'defect_found', 'condition_before', 'condition_after',
                'work_order', 'equipment', 'performer',
            ]]);
    }

    // ─── Cross-Tenant ───────────────────────────────────────────

    public function test_show_returns_404_for_cross_tenant_report(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $otherEquipment = Equipment::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $report = MaintenanceReport::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'work_order_id' => $otherWo->id,
            'equipment_id' => $otherEquipment->id,
        ]);

        $response = $this->getJson("/api/v1/maintenance-reports/{$report->id}");

        $response->assertNotFound();
    }

    // ─── Update ─────────────────────────────────────────────────

    public function test_update_modifies_report(): void
    {
        $report = MaintenanceReport::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'performed_by' => $this->user->id,
            'defect_found' => 'Original',
        ]);

        $response = $this->putJson("/api/v1/maintenance-reports/{$report->id}", [
            'defect_found' => 'Atualizado: sensor de temperatura danificado',
            'condition_after' => 'limited',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.defect_found', 'Atualizado: sensor de temperatura danificado')
            ->assertJsonPath('data.condition_after', 'limited');
    }

    // ─── Destroy ────────────────────────────────────────────────

    public function test_destroy_deletes_report(): void
    {
        $report = MaintenanceReport::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'performed_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/maintenance-reports/{$report->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('maintenance_reports', ['id' => $report->id]);
    }

    // ─── Approve ────────────────────────────────────────────────

    public function test_approve_sets_approver(): void
    {
        $report = MaintenanceReport::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'performed_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/maintenance-reports/{$report->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.approved_by', $this->user->id);
    }
}
