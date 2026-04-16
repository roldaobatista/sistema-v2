<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CalibrationReading;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalibrationFullFlowTest extends TestCase
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
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'precision_class' => 'III',
            'resolution' => 1.0,
            'capacity' => 10000,
        ]);
    }

    // ───── Wizard: Create Draft ─────────────────────────────────

    public function test_creates_calibration_draft_via_wizard(): void
    {
        $response = $this->postJson(
            "/api/v1/calibration/equipment/{$this->equipment->id}/draft",
            [
                'calibration_type' => 'initial',
                'calibration_location' => 'Laboratório ABC',
                'calibration_location_type' => 'laboratory',
                'verification_type' => 'initial',
                'precision_class' => 'III',
                'verification_division_e' => 1.0,
                'calibration_method' => 'comparison',
                'temperature' => 22.5,
                'humidity' => 55.0,
                'pressure' => 1013.25,
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.equipment_id', $this->equipment->id);
        $response->assertJsonPath('data.result', 'pending');
        $response->assertJsonPath('data.calibration_type', 'initial');

        $this->assertDatabaseHas('equipment_calibrations', [
            'equipment_id' => $this->equipment->id,
            'tenant_id' => $this->tenant->id,
            'result' => 'pending',
            'calibration_method' => 'comparison',
        ]);
    }

    // ───── Wizard: Store Readings ───────────────────────────────

    public function test_stores_calibration_readings(): void
    {
        $calibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'result' => 'pending',
        ]);

        $response = $this->postJson(
            "/api/v1/calibration/{$calibration->id}/readings",
            [
                'readings' => [
                    [
                        'reference_value' => 0,
                        'indication_increasing' => 0,
                        'indication_decreasing' => 0,
                        'unit' => 'g',
                    ],
                    [
                        'reference_value' => 5000,
                        'indication_increasing' => 5001,
                        'indication_decreasing' => 4999,
                        'unit' => 'g',
                    ],
                    [
                        'reference_value' => 10000,
                        'indication_increasing' => 10002,
                        'indication_decreasing' => 9998,
                        'unit' => 'g',
                    ],
                ],
            ]
        );

        $response->assertOk();
        $response->assertJsonCount(3, 'data.readings');

        $this->assertDatabaseCount('calibration_readings', 3);
        $this->assertDatabaseHas('calibration_readings', [
            'equipment_calibration_id' => $calibration->id,
            'reference_value' => 5000,
        ]);
    }

    // ───── Wizard: Validate ISO 17025 ───────────────────────────

    public function test_validates_iso_17025_requirements(): void
    {
        $calibration = EquipmentCalibration::factory()->withEnvironment()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'performed_by' => $this->user->id,
            'certificate_number' => 'CERT-0001',
            'laboratory' => 'Lab Teste',
            'calibration_location' => 'Sala 1',
            'calibration_method' => 'comparison',
            'conformity_declaration' => 'Aprovado conforme EMA',
        ]);

        // Create readings with expanded_uncertainty for ISO 17025
        CalibrationReading::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'equipment_calibration_id' => $calibration->id,
            'expanded_uncertainty' => 0.5,
        ]);

        $response = $this->getJson(
            "/api/v1/calibration/{$calibration->id}/validate-iso17025"
        );

        $response->assertOk();
        $response->assertJsonPath('data.valid', true);
        $response->assertJsonPath('data.missing', []);
    }

    // ───── Wizard: Prefill from Previous ────────────────────────

    public function test_prefills_wizard_data_from_previous_calibration(): void
    {
        // Create a previous completed calibration with certificate
        EquipmentCalibration::factory()->withEnvironment()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'certificate_number' => 'CERT-PREV-001',
            'calibration_date' => now()->subMonths(6),
            'temperature' => 21.0,
            'humidity' => 50.0,
            'pressure' => 1012.0,
        ]);

        $response = $this->getJson(
            "/api/v1/calibration/equipment/{$this->equipment->id}/prefill"
        );

        $response->assertOk();
        $response->assertJsonPath('data.prefilled', true);
        $response->assertJsonPath('data.data.prefilled_from_certificate', 'CERT-PREV-001');
        $response->assertJsonPath('data.data.temperature', fn ($v) => (float) $v === 21.0);
        $response->assertJsonPath('data.data.humidity', fn ($v) => (float) $v === 50.0);
    }

    public function test_prefill_returns_false_when_no_previous_calibration(): void
    {
        $newEquipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson(
            "/api/v1/calibration/equipment/{$newEquipment->id}/prefill"
        );

        $response->assertOk();
        $response->assertJsonPath('data.prefilled', false);
    }

    // ───── Validation Errors ────────────────────────────────────

    public function test_rejects_invalid_wizard_data(): void
    {
        $response = $this->postJson(
            "/api/v1/calibration/equipment/{$this->equipment->id}/draft",
            [
                'calibration_type' => 'invalid_type',
                'calibration_location_type' => 'mars_base',
                'precision_class' => 'X',
                'temperature' => 999,
                'humidity' => -10,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'calibration_type',
            'calibration_location_type',
            'precision_class',
            'temperature',
            'humidity',
        ]);
    }

    public function test_rejects_readings_without_reference_value(): void
    {
        $calibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
        ]);

        $response = $this->postJson(
            "/api/v1/calibration/{$calibration->id}/readings",
            [
                'readings' => [
                    [
                        'indication_increasing' => 100,
                        // reference_value missing
                    ],
                ],
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['readings.0.reference_value']);
    }

    public function test_rejects_empty_readings_array(): void
    {
        $calibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
        ]);

        $response = $this->postJson(
            "/api/v1/calibration/{$calibration->id}/readings",
            [
                'readings' => [],
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['readings']);
    }

    // ───── Send Certificate by Email ────────────────────────────

    public function test_sends_certificate_by_email(): void
    {
        Mail::fake();

        $calibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'certificate_number' => 'CERT-EMAIL-001',
            'certificate_pdf_path' => 'public/calibration-certificates/test-cert.pdf',
        ]);

        // Create the fake PDF file so the service doesn't throw
        $pdfDir = storage_path('app/public/calibration-certificates');
        if (! is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        file_put_contents($pdfDir.'/test-cert.pdf', '%PDF-1.4 fake content');

        $response = $this->postJson(
            "/api/v1/calibration/{$calibration->id}/send-certificate-email",
            [
                'email' => 'cliente@example.com',
                'subject' => 'Seu Certificado de Calibração',
                'message' => 'Segue o certificado em anexo.',
            ]
        );

        $response->assertOk();

        // Mail::raw is intercepted by the fake — we verify no exceptions were thrown
        // and the endpoint returned success.

        // Cleanup
        @unlink($pdfDir.'/test-cert.pdf');
    }

    public function test_send_certificate_email_rejects_invalid_email(): void
    {
        $calibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
        ]);

        $response = $this->postJson(
            "/api/v1/calibration/{$calibration->id}/send-certificate-email",
            [
                'email' => 'not-an-email',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    // ───── ISO 17025: Incomplete Calibration ────────────────────

    public function test_iso_17025_reports_missing_fields_for_incomplete_calibration(): void
    {
        $calibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'laboratory' => null,
            'certificate_number' => null,
            'calibration_method' => null,
            'temperature' => null,
            'humidity' => null,
            'performed_by' => null,
        ]);

        $response = $this->getJson(
            "/api/v1/calibration/{$calibration->id}/validate-iso17025"
        );

        $response->assertOk();
        $response->assertJsonPath('data.valid', false);

        $missing = $response->json('data.missing');
        $this->assertContains('laboratory_name', $missing);
        $this->assertContains('certificate_number', $missing);
        $this->assertContains('measurement_results', $missing);
        $this->assertContains('environmental_conditions', $missing);
    }
}
