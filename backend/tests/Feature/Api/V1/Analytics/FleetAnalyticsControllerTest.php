<?php

namespace Tests\Feature\Api\V1\Analytics;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\FleetVehicle;
use App\Models\Tenant;
use App\Models\TrafficFine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetAnalyticsControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([EnsureTenantScope::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    public function test_fleet_analytics_returns_expected_structure(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        $response = $this->getJson('/api/v1/fleet/analytics');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'cost_per_vehicle',
                'avg_consumption',
                'fines_by_month',
                'fuel_trend',
                'urgent_alerts',
            ]]);
    }

    public function test_fleet_analytics_cost_per_vehicle(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        $vehicle = FleetVehicle::factory()->create(['tenant_id' => $this->tenant->id]);

        // We use DB insertion or factory if exists. The controller joins 'fuel_logs' on 'fleet_vehicle_id'.
        DB::table('fuel_logs')->insert([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $vehicle->id,
            'liters' => 50,
            'distance_km' => 500,
            'total_value' => 250.00,
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/fleet/analytics');

        $response->assertOk();
        $costs = collect($response->json('data.cost_per_vehicle'));

        $vData = $costs->firstWhere('id', $vehicle->id);
        $this->assertNotNull($vData);
        $this->assertEquals(250.00, $vData['total_fuel_cost']);
        $this->assertEquals(50, $vData['total_liters']);
    }

    public function test_fleet_analytics_avg_consumption(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        $vehicle = FleetVehicle::factory()->create(['tenant_id' => $this->tenant->id]);

        // 500 km / 50 liters = 10 km/l
        DB::table('fuel_logs')->insert([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $vehicle->id,
            'liters' => 50,
            'distance_km' => 500,
            'total_value' => 250.00,
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/fleet/analytics');

        $response->assertOk();
        $consumptions = collect($response->json('data.avg_consumption'));

        $vData = $consumptions->firstWhere('plate', $vehicle->plate);
        $this->assertNotNull($vData);
        $this->assertEquals(10.0, $vData['km_per_liter']);
    }

    public function test_fleet_analytics_fines_by_month(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        $vehicle = FleetVehicle::factory()->create(['tenant_id' => $this->tenant->id]);

        TrafficFine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $vehicle->id,
            'amount' => 150.00,
            'fine_date' => now(),
        ]);

        $response = $this->getJson('/api/v1/fleet/analytics');

        $response->assertOk();
        $fines = collect($response->json('data.fines_by_month'));

        $monthStr = now()->format('Y-m');
        $fData = $fines->firstWhere('month', $monthStr);
        $this->assertNotNull($fData);
        $this->assertEquals(150.00, $fData['total_amount']);
        $this->assertEquals(1, $fData['count']);
    }

    public function test_fleet_analytics_urgent_alerts(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        FleetVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crlv_expiry' => now()->subDay(), // Expired
        ]);

        $response = $this->getJson('/api/v1/fleet/analytics');

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data.urgent_alerts')));
    }

    public function test_fleet_analytics_tenant_isolation(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->withoutMiddleware([CheckPermission::class]);

        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::factory()->create(['tenant_id' => $otherTenant->id]);

        DB::table('fuel_logs')->insert([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'liters' => 50,
            'distance_km' => 500,
            'total_value' => 250.00,
            'created_by' => User::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/fleet/analytics');

        $response->assertOk();
        $this->assertEmpty($response->json('data.cost_per_vehicle'));
    }

    public function test_fleet_analytics_requires_authentication(): void
    {
        // No Sanctum::actingAs
        $response = $this->getJson('/api/v1/fleet/analytics');
        $response->assertUnauthorized();
    }

    public function test_fleet_analytics_respects_permissions_403(): void
    {
        Gate::before(fn () => false);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/fleet/analytics');

        $response->assertForbidden();
    }
}
