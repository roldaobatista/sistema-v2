<?php

namespace Tests\Feature\Performance;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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

    }

    // ── N+1 Query Detection ──

    public function test_work_orders_index_no_n_plus_1(): void
    {
        WorkOrder::factory()->count(10)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        DB::enableQueryLog();
        $this->actingAs($this->user)->getJson('/api/v1/work-orders');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // A well-optimized endpoint should have < 15 queries for 10 records
        $this->assertLessThan(20, count($queries), 'Possible N+1 query detected');
    }

    public function test_customers_index_no_n_plus_1(): void
    {
        Customer::factory()->count(10)->create(['tenant_id' => $this->tenant->id]);

        DB::enableQueryLog();
        $this->actingAs($this->user)->getJson('/api/v1/customers');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThan(15, count($queries), 'Possible N+1 query detected');
    }

    public function test_quotes_index_no_n_plus_1(): void
    {
        Quote::factory()->count(10)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        DB::enableQueryLog();
        $this->actingAs($this->user)->getJson('/api/v1/quotes');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThan(15, count($queries), 'Possible N+1 query detected');
    }

    // ── Response Time ──

    public function test_work_orders_index_responds_under_2s(): void
    {
        WorkOrder::factory()->count(20)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $start = microtime(true);
        $this->actingAs($this->user)->getJson('/api/v1/work-orders');
        $duration = microtime(true) - $start;

        $this->assertLessThan(2.0, $duration, 'Response took too long');
    }

    public function test_customers_index_responds_under_2s(): void
    {
        Customer::factory()->count(20)->create(['tenant_id' => $this->tenant->id]);

        $start = microtime(true);
        $this->actingAs($this->user)->getJson('/api/v1/customers');
        $duration = microtime(true) - $start;

        $this->assertLessThan(2.0, $duration, 'Response took too long');
    }

    public function test_dashboard_responds_under_3s(): void
    {
        $start = microtime(true);
        $this->actingAs($this->user)->getJson('/api/v1/dashboard');
        $duration = microtime(true) - $start;

        $this->assertLessThan(3.0, $duration, 'Dashboard too slow');
    }

    // ── Memory ──

    public function test_work_orders_export_memory_usage(): void
    {
        WorkOrder::factory()->count(50)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $memBefore = memory_get_usage(true);
        $this->actingAs($this->user)->getJson('/api/v1/work-orders?per_page=50');
        $memAfter = memory_get_usage(true);

        $memUsedMb = ($memAfter - $memBefore) / 1024 / 1024;
        $this->assertLessThan(50, $memUsedMb, 'Excessive memory usage');
    }

    // ── Bulk Operations ──

    public function test_bulk_create_customers_performance(): void
    {
        $start = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        }

        $duration = microtime(true) - $start;
        $this->assertLessThan(5.0, $duration, 'Bulk creation too slow');
    }

    public function test_concurrent_user_requests(): void
    {
        $users = User::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        foreach ($users as $u) {
            $u->tenants()->attach($this->tenant->id, ['is_default' => true]);
            $u->assignRole('admin');
        }

        $start = microtime(true);
        foreach ($users as $u) {
            $this->actingAs($u)->getJson('/api/v1/customers');
        }
        $duration = microtime(true) - $start;

        $this->assertLessThan(5.0, $duration, 'Sequential concurrent requests too slow');
    }

    public function test_pagination_works_correctly(): void
    {
        Customer::factory()->count(25)->create(['tenant_id' => $this->tenant->id]);

        $page1 = $this->actingAs($this->user)->getJson('/api/v1/customers?per_page=10&page=1');
        $page1->assertOk();
        $this->assertCount(10, $page1->json('data'));

        $page2 = $this->actingAs($this->user)->getJson('/api/v1/customers?per_page=10&page=2');
        $page2->assertOk();
        $this->assertCount(10, $page2->json('data'));
    }
}
