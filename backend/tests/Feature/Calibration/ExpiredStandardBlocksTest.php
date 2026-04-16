<?php

namespace Tests\Feature\Calibration;

use App\Models\CertificateEmissionChecklist;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\StandardWeight;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CalibrationCertificateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Mockery;
use Tests\TestCase;

class ExpiredStandardBlocksTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private EquipmentCalibration $calibration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
        $this->calibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment->id,
            'performed_by' => $this->user->id,
            'laboratory' => 'Test Lab',
        ]);

        // Create approved emission checklist
        CertificateEmissionChecklist::create([
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
            'verified_at' => now(),
        ]);
    }

    public function test_expired_standard_blocks_certificate_generation(): void
    {
        $expiredWeight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'certificate_expiry' => now()->subMonth(),
        ]);
        $this->calibration->standardWeights()->attach($expiredWeight->id);

        $service = new CalibrationCertificateService;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Padrões com certificado vencido');

        $service->generate($this->calibration);
    }

    public function test_valid_standard_allows_certificate_generation(): void
    {
        $validWeight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'certificate_expiry' => now()->addYear(),
        ]);
        $this->calibration->standardWeights()->attach($validWeight->id);

        // Isola o weight gate do rendering real do PDF (view Blade depende de layout/fonts)
        $pdfMock = Mockery::mock(DomPdfInstance::class);
        $pdfMock->shouldReceive('setPaper')->once();
        $pdfMock->shouldReceive('setOption')->times(3);
        Pdf::shouldReceive('loadView')->once()->andReturn($pdfMock);

        $service = new CalibrationCertificateService;
        $result = $service->generate($this->calibration);

        // Se chegou aqui sem DomainException, o gate de padrão vencido foi aprovado
        $this->assertSame($pdfMock, $result);
        $this->assertTrue(
            $this->calibration->fresh()->standardWeights->contains($validWeight->id),
            'Padrão válido deve permanecer associado à calibração após geração'
        );
    }

    public function test_standard_without_expiry_is_allowed(): void
    {
        $noExpiryWeight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'certificate_expiry' => null,
        ]);
        $this->calibration->standardWeights()->attach($noExpiryWeight->id);

        $pdfMock = Mockery::mock(DomPdfInstance::class);
        $pdfMock->shouldReceive('setPaper')->once();
        $pdfMock->shouldReceive('setOption')->times(3);
        Pdf::shouldReceive('loadView')->once()->andReturn($pdfMock);

        $service = new CalibrationCertificateService;
        $result = $service->generate($this->calibration);

        // Padrão sem certificate_expiry (definido como null) não pode disparar o gate de vencimento
        $this->assertSame($pdfMock, $result);
        $this->assertNull(
            $noExpiryWeight->fresh()->certificate_expiry,
            'Peso sem expiração deve permanecer sem data de validade'
        );
    }

    public function test_mix_of_expired_and_valid_weights_blocks_generation(): void
    {
        // Caso edge: um padrão válido + um vencido — DEVE bloquear
        $validWeight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'VALID-001',
            'certificate_expiry' => now()->addMonths(6),
        ]);
        $expiredWeight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'EXPIRED-001',
            'certificate_expiry' => now()->subDays(1),
        ]);
        $this->calibration->standardWeights()->attach([$validWeight->id, $expiredWeight->id]);

        $service = new CalibrationCertificateService;

        try {
            $service->generate($this->calibration);
            $this->fail('Expected DomainException due to expired standard weight in the mix');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('vencido', $e->getMessage());
            $this->assertStringContainsString('EXPIRED-001', $e->getMessage());
            $this->assertStringNotContainsString('VALID-001', $e->getMessage());
        }
    }
}
