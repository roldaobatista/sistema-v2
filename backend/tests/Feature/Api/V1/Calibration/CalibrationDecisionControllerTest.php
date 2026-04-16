<?php

namespace Tests\Feature\Api\V1\Calibration;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CalibrationDecisionLog;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalibrationDecisionControllerTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $user;

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
    }

    private function makeCalibration(array $overrides = [], ?Tenant $tenant = null): EquipmentCalibration
    {
        $tenant = $tenant ?? $this->tenant;
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

        return EquipmentCalibration::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'equipment_id' => $equipment->id,
            'max_permissible_error' => 1.0,
            'max_error_found' => 0.3,
            'uncertainty' => 0.2,
            'mass_unit' => 'g',
            'decision_rule' => 'simple',
        ], $overrides));
    }

    private function url(EquipmentCalibration $c): string
    {
        return "/api/v1/equipment-calibrations/{$c->id}/evaluate-decision";
    }

    // 1. requires authentication
    public function test_it_requires_authentication(): void
    {
        // Reset sanctum
        $this->app['auth']->forgetGuards();
        $cal = $this->makeCalibration();

        $resp = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson($this->url($cal), ['rule' => 'simple', 'coverage_factor_k' => 2.0]);

        $resp->assertStatus(401);
    }

    // 2. requires permission (form request authorize)
    public function test_it_requires_permission(): void
    {
        // Clear Gate::before callbacks set by parent setUp so $user->can() runs through Spatie
        $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);
        $ref = new \ReflectionClass($gate);
        $prop = $ref->getProperty('beforeCallbacks');
        $prop->setAccessible(true);
        $prop->setValue($gate, []);
        Gate::define('calibration.certificate.manage', fn () => false);

        $cal = $this->makeCalibration();
        $resp = $this->postJson($this->url($cal), ['rule' => 'simple', 'coverage_factor_k' => 2.0]);

        $resp->assertStatus(403);
    }

    // 3. cross-tenant returns 404 (BelongsToTenant scope)
    public function test_it_returns_404_for_cross_tenant_calibration(): void
    {
        $cal = $this->makeCalibration([], $this->otherTenant);

        $resp = $this->postJson($this->url($cal), ['rule' => 'simple', 'coverage_factor_k' => 2.0]);

        $resp->assertStatus(404);
    }

    // 4. validates required fields
    public function test_it_validates_required_fields(): void
    {
        $cal = $this->makeCalibration();

        $resp = $this->postJson($this->url($cal), []);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['rule', 'coverage_factor_k']);
    }

    // 5. guard_band requires mode + value
    public function test_it_validates_guard_band_params_when_rule_is_guard_band(): void
    {
        $cal = $this->makeCalibration();

        $resp = $this->postJson($this->url($cal), [
            'rule' => 'guard_band',
            'coverage_factor_k' => 2.0,
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['guard_band_mode', 'guard_band_value']);
    }

    // 6. shared_risk requires alpha + beta
    public function test_it_validates_shared_risk_params_when_rule_is_shared_risk(): void
    {
        $cal = $this->makeCalibration();

        $resp = $this->postJson($this->url($cal), [
            'rule' => 'shared_risk',
            'coverage_factor_k' => 2.0,
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['producer_risk_alpha', 'consumer_risk_beta']);
    }

    // 7. enum value enforced
    public function test_it_validates_enum_for_rule(): void
    {
        $cal = $this->makeCalibration();

        $resp = $this->postJson($this->url($cal), [
            'rule' => 'simple_acceptance',
            'coverage_factor_k' => 2.0,
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['rule']);
    }

    // 8. simple → accept persisted
    public function test_it_persists_simple_decision_accept(): void
    {
        $cal = $this->makeCalibration([
            'max_permissible_error' => 1.0,
            'max_error_found' => 0.3,
            'uncertainty' => 0.5,
        ]);

        $resp = $this->postJson($this->url($cal), [
            'rule' => 'simple',
            'coverage_factor_k' => 2.0,
            'confidence_level' => 95.45,
        ]);

        $resp->assertOk();
        $this->assertDatabaseHas('equipment_calibrations', [
            'id' => $cal->id,
            'decision_result' => 'accept',
            'decision_rule' => 'simple',
        ]);
    }

    // 9. guard_band → warn persisted with applied w
    public function test_it_persists_guard_band_decision_warn(): void
    {
        $cal = $this->makeCalibration([
            'max_permissible_error' => 1.0,
            'max_error_found' => 0.5,
            'uncertainty' => 0.3,
        ]);

        $resp = $this->postJson($this->url($cal), [
            'rule' => 'guard_band',
            'coverage_factor_k' => 2.0,
            'guard_band_mode' => 'k_times_u',
            'guard_band_value' => 1.0,
        ]);

        $resp->assertOk();
        $cal->refresh();
        $this->assertSame('warn', $cal->decision_result);
        $this->assertNotNull($cal->decision_guard_band_applied);
        $this->assertEquals(0.3, (float) $cal->decision_guard_band_applied);
    }

    // 10. shared_risk persists z + pfa
    public function test_it_persists_shared_risk_decision_with_z_and_pfa(): void
    {
        $cal = $this->makeCalibration([
            'max_permissible_error' => 1.0,
            'max_error_found' => 0.3,
            'uncertainty' => 0.5,
        ]);

        $resp = $this->postJson($this->url($cal), [
            'rule' => 'shared_risk',
            'coverage_factor_k' => 2.0,
            'producer_risk_alpha' => 0.05,
            'consumer_risk_beta' => 0.05,
        ]);

        $resp->assertOk();
        $cal->refresh();
        $this->assertSame('accept', $cal->decision_result);
        $this->assertNotNull($cal->decision_z_value);
        $this->assertNotNull($cal->decision_false_accept_prob);
    }

    // 11. creates a calibration_decision_log row
    public function test_it_creates_calibration_decision_log(): void
    {
        $cal = $this->makeCalibration();
        $before = CalibrationDecisionLog::count();

        $resp = $this->postJson($this->url($cal), [
            'rule' => 'simple',
            'coverage_factor_k' => 2.0,
        ]);

        $resp->assertOk();
        $this->assertSame($before + 1, CalibrationDecisionLog::count());

        $log = CalibrationDecisionLog::latest('id')->first();
        $this->assertSame($cal->id, $log->equipment_calibration_id);
        $this->assertSame('simple', $log->decision_rule);
        $this->assertSame('1.0', $log->engine_version);
        $this->assertArrayHasKey('measured_error', $log->inputs);
        $this->assertArrayHasKey('result', $log->outputs);
    }

    // 12. re-evaluation overwrites and creates a new log
    public function test_it_updates_existing_decision_on_reevaluation(): void
    {
        $cal = $this->makeCalibration([
            'max_permissible_error' => 1.0,
            'max_error_found' => 0.3,
            'uncertainty' => 0.5,
        ]);

        // first
        $this->postJson($this->url($cal), [
            'rule' => 'simple',
            'coverage_factor_k' => 2.0,
        ])->assertOk();

        // second (different rule)
        $this->postJson($this->url($cal), [
            'rule' => 'guard_band',
            'coverage_factor_k' => 2.0,
            'guard_band_mode' => 'fixed_abs',
            'guard_band_value' => 0.05,
        ])->assertOk();

        $cal->refresh();
        $this->assertSame('guard_band', $cal->decision_rule);
        $this->assertSame(2, CalibrationDecisionLog::where('equipment_calibration_id', $cal->id)->count());
    }

    // 13. resource block exposes the decision payload
    public function test_it_returns_decision_block_in_resource(): void
    {
        $cal = $this->makeCalibration();

        $resp = $this->postJson($this->url($cal), [
            'rule' => 'simple',
            'coverage_factor_k' => 2.0,
            'confidence_level' => 95.45,
            'notes' => 'auditoria 2026-04-10',
        ]);

        $resp->assertOk()->assertJsonStructure([
            'data' => [
                'id',
                'decision' => [
                    'rule', 'result', 'coverage_factor_k', 'confidence_level',
                    'guard_band_mode', 'guard_band_value', 'guard_band_applied',
                    'producer_risk_alpha', 'consumer_risk_beta',
                    'z_value', 'false_accept_probability',
                    'calculated_at', 'notes',
                ],
            ],
        ]);
    }

    // 14. shared_risk reject case (P_fr <= alpha)
    public function test_it_persists_shared_risk_reject(): void
    {
        $cal = $this->makeCalibration([
            'max_permissible_error' => 1.0,
            'max_error_found' => 1.2,
            'uncertainty' => 0.1,
        ]);

        $resp = $this->postJson($this->url($cal), [
            'rule' => 'shared_risk',
            'coverage_factor_k' => 2.0,
            'producer_risk_alpha' => 0.05,
            'consumer_risk_beta' => 0.05,
        ]);

        $resp->assertOk();
        $cal->refresh();
        $this->assertSame('reject', $cal->decision_result);
    }
}
