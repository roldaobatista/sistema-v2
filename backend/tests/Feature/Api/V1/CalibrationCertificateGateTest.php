<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CertificateEmissionChecklist;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CalibrationCertificateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalibrationCertificateGateTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private EquipmentCalibration $calibration;

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

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
        $this->calibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment->id,
            'performed_by' => $this->user->id,
        ]);
    }

    public function test_generate_throws_without_emission_checklist(): void
    {
        $service = app(CalibrationCertificateService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('O checklist de emissão do certificado deve ser preenchido e aprovado');

        $service->generate($this->calibration);
    }

    public function test_generate_throws_with_incomplete_checklist(): void
    {
        CertificateEmissionChecklist::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_calibration_id' => $this->calibration->id,
            'verified_by' => $this->user->id,
            'equipment_identified' => true,
            'scope_defined' => true,
            'critical_analysis_done' => true,
            'procedure_defined' => true,
            'standards_traceable' => true,
            'raw_data_recorded' => true,
            'uncertainty_calculated' => false, // incomplete
            'adjustment_documented' => true,
            'no_undue_interval' => true,
            'conformity_declaration_valid' => true,
            'accreditation_mark_correct' => true,
            'approved' => false,
        ]);

        $service = app(CalibrationCertificateService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Incerteza calculada');

        $service->generate($this->calibration);
    }

    public function test_generate_endpoint_returns_422_without_checklist(): void
    {
        $response = $this->postJson("/api/v1/calibration/{$this->calibration->id}/generate-certificate");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'O checklist de emissão do certificado deve ser preenchido e aprovado antes de gerar o PDF.']);
    }

    public function test_service_passes_gate_with_approved_checklist(): void
    {
        CertificateEmissionChecklist::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_calibration_id' => $this->calibration->id,
            'verified_by' => $this->user->id,
            'equipment_identified' => true,
            'scope_defined' => true,
            'critical_analysis_done' => true,
            'procedure_defined' => true,
            'standards_traceable' => true,
            'raw_data_recorded' => true,
            'uncertainty_calculated' => true,
            'adjustment_documented' => true,
            'no_undue_interval' => true,
            'conformity_declaration_valid' => true,
            'accreditation_mark_correct' => true,
            'approved' => true,
        ]);

        // Verify the gate passes (no DomainException) by mocking the PDF layer
        Pdf::shouldReceive('loadView')
            ->once()
            ->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('setOption')->andReturnSelf();

        $service = app(CalibrationCertificateService::class);

        // Should NOT throw DomainException — gate passes
        $pdf = $service->generate($this->calibration);
        $this->assertNotNull($pdf);
    }
}
