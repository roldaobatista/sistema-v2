<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\RoutingOptimizationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoutingOptimizationServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $technician;

    private RoutingOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->service = app(RoutingOptimizationService::class);
    }

    public function test_optimize_daily_plan_uses_customer_coordinates_and_persists_the_plan(): void
    {
        $date = '2026-03-20';

        $nearCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'latitude' => 0.1,
            'longitude' => 0.1,
        ]);
        $midCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'latitude' => 2.0,
            'longitude' => 2.0,
        ]);
        $farCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'latitude' => 10.0,
            'longitude' => 10.0,
        ]);

        $nearOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $nearCustomer->id,
            'assigned_to' => $this->technician->id,
            'scheduled_date' => "{$date} 08:00:00",
        ]);
        $midOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $midCustomer->id,
            'assigned_to' => $this->technician->id,
            'scheduled_date' => "{$date} 09:00:00",
        ]);
        $farOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $farCustomer->id,
            'assigned_to' => $this->technician->id,
            'scheduled_date' => "{$date} 10:00:00",
        ]);

        $optimizedPath = $this->service->optimizeDailyPlan($this->tenant->id, $this->technician->id, $date);

        $this->assertCount(3, $optimizedPath);
        $this->assertSame(
            [$nearOrder->id, $midOrder->id, $farOrder->id],
            array_column($optimizedPath, 'work_order_id')
        );
        $this->assertSame(0.1, $optimizedPath[0]['lat']);
        $this->assertSame(0.1, $optimizedPath[0]['lng']);
        $this->assertSame($nearCustomer->name, $optimizedPath[0]['customer']);

        $routePlan = DB::table('routes_planning')
            ->where('tenant_id', $this->tenant->id)
            ->where('tech_id', $this->technician->id)
            ->where('date', $date)
            ->first();

        $this->assertNotNull($routePlan);
        $this->assertJson($routePlan->optimized_path_json);
        $this->assertNotNull($routePlan->total_distance_km);
        $this->assertFalse(property_exists($routePlan, 'total_duration_minutes'));
        $this->assertFalse(property_exists($routePlan, 'status'));
    }

    public function test_optimize_daily_plan_does_not_leak_other_tenant_work_orders(): void
    {
        $date = '2026-03-20';

        $tenantCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'latitude' => 1.0,
            'longitude' => 1.0,
        ]);
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'latitude' => 0.01,
            'longitude' => 0.01,
        ]);

        $tenantOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $tenantCustomer->id,
            'assigned_to' => $this->technician->id,
            'scheduled_date' => "{$date} 08:00:00",
        ]);
        $otherTenantOrder = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'assigned_to' => $this->technician->id,
            'scheduled_date' => "{$date} 09:00:00",
        ]);

        $optimizedPath = $this->service->optimizeDailyPlan($this->tenant->id, $this->technician->id, $date);

        $this->assertCount(1, $optimizedPath);
        $this->assertSame([$tenantOrder->id], array_column($optimizedPath, 'work_order_id'));
        $this->assertNotContains($otherTenantOrder->id, array_column($optimizedPath, 'work_order_id'));
    }
}
