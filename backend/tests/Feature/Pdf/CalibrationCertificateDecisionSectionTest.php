<?php

namespace Tests\Feature\Pdf;

use App\Models\CertificateEmissionChecklist;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CalibrationCertificateService;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Snapshots HTML do bloco "Declaração de Conformidade" do certificado de calibração.
 *
 * Cobre: ISO/IEC 17025 §7.8.6 + ILAC G8:09/2019 + ILAC P14:09/2020 + JCGM 106:2012
 */
class CalibrationCertificateDecisionSectionTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // O blade do certificado acessa muitos atributos opcionais do tenant/equipment;
        // desativar strict mode apenas neste teste evita erros falso-positivos de atributo ausente.
        Model::preventAccessingMissingAttributes(false);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    protected function tearDown(): void
    {
        Model::preventAccessingMissingAttributes(true);
        parent::tearDown();
    }

    private function makeCal(array $overrides = []): EquipmentCalibration
    {
        $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

        $cal = EquipmentCalibration::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment->id,
            'max_permissible_error' => 1.0,
            'max_error_found' => 0.3,
            'mass_unit' => 'g',
            'decision_rule' => 'simple',
            'decision_result' => 'accept',
            'coverage_factor_k' => 2.0,
            'confidence_level' => 95.45,
            'uncertainty' => 0.2,
        ], $overrides));

        // Checklist de emissão aprovado (gate pré-requisito do service)
        CertificateEmissionChecklist::create([
            'tenant_id' => $this->tenant->id,
            'equipment_calibration_id' => $cal->id,
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

        return $cal->fresh(['emissionChecklist']);
    }

    public function test_it_prints_simple_rule_with_k_and_confidence(): void
    {
        $cal = $this->makeCal([
            'decision_rule' => 'simple',
            'decision_result' => 'accept',
        ]);

        $service = app(CalibrationCertificateService::class);
        $pdf = $service->generate($cal);
        $html = $pdf->getDomPDF()->output_html();

        $this->assertStringContainsString('2,00', $html); // k
        $this->assertStringContainsString('95,45%', $html); // confidence
        $this->assertStringContainsString('CONFORME', $html);
        $this->assertStringContainsString('Aceitação simples', $html);
    }

    public function test_it_prints_guard_band_with_applied_w(): void
    {
        $cal = $this->makeCal([
            'decision_rule' => 'guard_band',
            'guard_band_mode' => 'k_times_u',
            'guard_band_value' => 1.0,
            'decision_guard_band_applied' => 0.20,
            'decision_result' => 'warn',
        ]);

        $service = app(CalibrationCertificateService::class);
        $pdf = $service->generate($cal);
        $html = $pdf->getDomPDF()->output_html();

        $this->assertStringContainsString('Banda de guarda', $html);
        $this->assertStringContainsString('k × U', $html);
        $this->assertStringContainsString('ZONA DE GUARDA', $html);
    }

    public function test_it_prints_shared_risk_with_z_and_pfa(): void
    {
        $cal = $this->makeCal([
            'decision_rule' => 'shared_risk',
            'producer_risk_alpha' => 0.05,
            'consumer_risk_beta' => 0.05,
            'decision_z_value' => 2.8,
            'decision_false_accept_prob' => 0.0026,
            'decision_result' => 'accept',
        ]);

        $service = app(CalibrationCertificateService::class);
        $pdf = $service->generate($cal);
        $html = $pdf->getDomPDF()->output_html();

        $this->assertStringContainsString('Risco compartilhado', $html);
        $this->assertStringContainsString('z calculado', $html);
        $this->assertStringContainsString('P(cauda)', $html);
        $this->assertStringContainsString('CONFORME', $html);
    }

    public function test_it_refuses_emission_when_decision_not_evaluated(): void
    {
        $cal = $this->makeCal([
            'decision_rule' => 'guard_band',
            'decision_result' => null,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/não foi avaliada/');

        app(CalibrationCertificateService::class)->generate($cal);
    }
}
