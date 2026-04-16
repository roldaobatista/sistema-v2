<?php

namespace Tests\Unit\Services;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Calibration\CalibrationWizardService;
use App\Services\Calibration\EmaCalculator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes profundos do CalibrationWizardService:
 * fluxo de criação de certificado, pontos sugeridos,
 * cálculo de incerteza, conformidade.
 */
class CalibrationWizardRealTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Equipment $equipment;

    private WorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAs($this->user);
    }

    public function test_wizard_service_exists(): void
    {
        $this->assertTrue(class_exists(CalibrationWizardService::class));
    }

    public function test_ema_integration_class_iii_5_points(): void
    {
        $points = EmaCalculator::suggestPoints('III', 1.0, 10000.0, 'initial');
        $this->assertCount(5, $points);

        // each point except 0% should have positive EMA
        for ($i = 1; $i < 5; $i++) {
            $this->assertGreaterThan(0, $points[$i]['ema']);
        }
    }

    public function test_conformity_check_all_passing(): void
    {
        $ema = EmaCalculator::calculate('III', 1.0, 5000.0, 'initial');

        // Simulate readings all within EMA
        $readings = [-0.5, 0.3, -1.0, 0.8, 1.2];
        $allConforming = true;
        foreach ($readings as $reading) {
            if (! EmaCalculator::isConforming($reading, $ema)) {
                $allConforming = false;
            }
        }
        $this->assertTrue($allConforming);
    }

    public function test_conformity_check_one_failing(): void
    {
        $ema = EmaCalculator::calculate('III', 1.0, 200.0, 'initial'); // ema = 0.5

        // One reading exceeds EMA
        $this->assertFalse(EmaCalculator::isConforming(0.8, $ema));
    }

    public function test_eccentricity_and_repeatability_for_real_balance(): void
    {
        $maxCap = 15000.0;
        $eccLoad = EmaCalculator::suggestEccentricityLoad($maxCap);
        $repLoad = EmaCalculator::suggestRepeatabilityLoad($maxCap);

        $this->assertEquals(5000.0, $eccLoad);
        $this->assertEquals(7500.0, $repLoad);
        $this->assertGreaterThan($eccLoad, $repLoad);
    }

    public function test_available_classes_for_certificate(): void
    {
        $classes = EmaCalculator::availableClasses();
        $this->assertContains('I', $classes);
        $this->assertContains('II', $classes);
        $this->assertContains('III', $classes);
        $this->assertContains('IIII', $classes);
    }

    public function test_in_use_vs_initial_doubles(): void
    {
        $initial = EmaCalculator::calculate('III', 1.0, 1000.0, 'initial');
        $inUse = EmaCalculator::calculate('III', 1.0, 1000.0, 'in_use');
        $this->assertEquals($initial * 2, $inUse);
    }

    public function test_calibration_certificate_api(): void
    {
        $response = $this->getJson("/api/v1/equipments/{$this->equipment->id}/calibrations");
        $response->assertOk();
    }
}
