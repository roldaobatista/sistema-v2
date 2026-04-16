<?php

namespace Tests\Feature\Performance;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderPerformanceTest extends TestCase
{
    private Tenant $tenant;

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
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_query_count_is_bounded(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->count(10)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/work-orders?per_page=10');
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // With eager loading, should not exceed ~15 queries for 10 OS
        $this->assertLessThanOrEqual(20, $queryCount, "Index produced {$queryCount} queries (expected <= 20)");
    }

    public function test_show_query_count_is_bounded(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        DB::enableQueryLog();

        $response = $this->getJson("/api/v1/work-orders/{$wo->id}");
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // With eager loading, show should not exceed ~20 queries
        $this->assertLessThanOrEqual(25, $queryCount, "Show produced {$queryCount} queries (expected <= 25)");
    }

    public function test_dashboard_stats_responds_quickly(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->count(20)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $start = microtime(true);
        $response = $this->getJson('/api/v1/work-orders-dashboard-stats');
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(3000, $duration, "Dashboard stats took {$duration}ms (expected < 3000ms)");
    }
}
