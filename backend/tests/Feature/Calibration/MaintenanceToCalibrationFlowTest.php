<?php

namespace Tests\Feature\Calibration;

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

class MaintenanceToCalibrationFlowTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private WorkOrder $workOrder;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'equipment_id' => $this->equipment->id,
            'requires_maintenance' => true,
            'service_type' => 'calibracao',
        ]);
    }

    public function test_maintenance_report_with_requires_calibration_flag(): void
    {
        $response = $this->postJson('/api/v1/maintenance-reports', [
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'defect_found' => 'Célula de carga com drift excessivo',
            'probable_cause' => 'Desgaste por sobrecarga repetida',
            'corrective_action' => 'Substituição da célula de carga principal',
            'parts_replaced' => [['name' => 'Célula de carga 50kg', 'quantity' => 1, 'reason' => 'Drift > 2e']],
            'condition_before' => 'defective',
            'condition_after' => 'requires_calibration',
            'requires_calibration_after' => true,
            'requires_ipem_verification' => false,
            'seal_status' => 'replaced',
            'new_seal_number' => 'SEAL-2026-0042',
        ]);

        $response->assertStatus(201);

        $report = MaintenanceReport::first();
        $this->assertTrue($report->requires_calibration_after);
        $this->assertEquals('Célula de carga com drift excessivo', $report->defect_found);
        $this->assertEquals('replaced', $report->seal_status);
        $this->assertEquals('SEAL-2026-0042', $report->new_seal_number);
    }

    public function test_maintenance_report_with_ipem_verification_required(): void
    {
        $this->postJson('/api/v1/maintenance-reports', [
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'defect_found' => 'Lacre rompido',
            'probable_cause' => 'Tentativa de ajuste não autorizado',
            'corrective_action' => 'Selagem e ajuste em bancada',
            'condition_before' => 'degraded',
            'condition_after' => 'functional',
            'requires_calibration_after' => true,
            'requires_ipem_verification' => true,
        ])->assertStatus(201);

        $report = MaintenanceReport::first();
        $this->assertTrue($report->requires_ipem_verification);
    }
}
