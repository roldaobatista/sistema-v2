<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CertificateEmissionChecklist;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateEmissionChecklistControllerTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

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
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
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
        ]);
    }

    // ─── Store/Update ───────────────────────────────────────────

    public function test_store_creates_complete_checklist_as_approved(): void
    {
        $payload = [
            'equipment_calibration_id' => $this->calibration->id,
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
        ];

        $response = $this->postJson('/api/v1/certificate-emission-checklist', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => [
                'id', 'equipment_calibration_id', 'verified_by',
                'equipment_identified', 'scope_defined', 'critical_analysis_done',
                'procedure_defined', 'standards_traceable', 'raw_data_recorded',
                'uncertainty_calculated', 'adjustment_documented', 'no_undue_interval',
                'conformity_declaration_valid', 'accreditation_mark_correct',
                'approved', 'verified_at', 'verifier',
            ]])
            ->assertJsonPath('data.approved', true)
            ->assertJsonPath('data.verified_by', $this->user->id);
    }

    public function test_store_creates_incomplete_checklist_as_not_approved(): void
    {
        $payload = [
            'equipment_calibration_id' => $this->calibration->id,
            'equipment_identified' => true,
            'scope_defined' => true,
            'critical_analysis_done' => true,
            'procedure_defined' => true,
            'standards_traceable' => true,
            'raw_data_recorded' => true,
            'uncertainty_calculated' => false,  // não calculou incerteza
            'adjustment_documented' => true,
            'no_undue_interval' => true,
            'conformity_declaration_valid' => true,
            'accreditation_mark_correct' => true,
        ];

        $response = $this->postJson('/api/v1/certificate-emission-checklist', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.approved', false)
            ->assertJsonPath('data.uncertainty_calculated', false);
    }

    public function test_store_updates_existing_checklist(): void
    {
        CertificateEmissionChecklist::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_calibration_id' => $this->calibration->id,
            'verified_by' => $this->user->id,
            'uncertainty_calculated' => false,
            'approved' => false,
        ]);

        $payload = [
            'equipment_calibration_id' => $this->calibration->id,
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
        ];

        $response = $this->postJson('/api/v1/certificate-emission-checklist', $payload);

        $response->assertOk()
            ->assertJsonPath('data.approved', true)
            ->assertJsonPath('data.uncertainty_calculated', true);

        // Deve ter só 1 registro (upsert)
        $this->assertDatabaseCount('certificate_emission_checklists', 1);
    }

    public function test_store_returns_422_for_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/certificate-emission-checklist', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'equipment_calibration_id',
                'equipment_identified',
                'scope_defined',
            ]);
    }

    // ─── Show ───────────────────────────────────────────────────

    public function test_show_returns_checklist_for_calibration(): void
    {
        CertificateEmissionChecklist::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_calibration_id' => $this->calibration->id,
            'verified_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/certificate-emission-checklist/{$this->calibration->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'equipment_calibration_id', 'approved',
                'verifier',
            ]]);
    }

    public function test_show_returns_404_when_no_checklist(): void
    {
        $response = $this->getJson('/api/v1/certificate-emission-checklist/99999');

        $response->assertNotFound();
    }

    // ─── Cross-Tenant ───────────────────────────────────────────

    public function test_show_returns_404_for_cross_tenant_checklist(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherEquipment = Equipment::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $otherCalibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'equipment_id' => $otherEquipment->id,
        ]);

        CertificateEmissionChecklist::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'equipment_calibration_id' => $otherCalibration->id,
        ]);

        $response = $this->getJson("/api/v1/certificate-emission-checklist/{$otherCalibration->id}");

        $response->assertNotFound();
    }
}
