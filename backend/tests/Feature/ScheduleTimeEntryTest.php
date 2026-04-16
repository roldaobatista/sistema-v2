<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Schedule CRUD + TimeEntry start/stop + conflicts + workload.
 */
class ScheduleTimeEntryTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── SCHEDULES ──

    public function test_list_schedules(): void
    {
        $response = $this->getJson('/api/v1/schedules');
        $response->assertOk();
    }

    public function test_unified_schedule_view(): void
    {
        $response = $this->getJson('/api/v1/schedules-unified');
        $response->assertOk();
    }

    public function test_create_schedule(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/schedules', [
            'work_order_id' => $wo->id,
            'technician_id' => $this->user->id,
            'title' => 'Visita Técnica',
            'scheduled_start' => now()->addDays(3)->setHour(9)->format('Y-m-d H:i:s'),
            'scheduled_end' => now()->addDays(3)->setHour(10)->format('Y-m-d H:i:s'),
        ]);
        $response->assertCreated();
    }

    public function test_schedule_conflicts(): void
    {
        $start = now()->addDay()->setHour(9)->format('Y-m-d H:i:s');
        $end = now()->addDay()->setHour(10)->format('Y-m-d H:i:s');

        $response = $this->getJson('/api/v1/schedules/conflicts?'.http_build_query([
            'technician_id' => $this->user->id,
            'start' => $start,
            'end' => $end,
        ]));
        $response->assertOk();
    }

    public function test_schedule_workload_summary(): void
    {
        $response = $this->getJson('/api/v1/schedules/workload');
        $response->assertOk();
    }

    // ── TIME ENTRIES ──

    public function test_list_time_entries(): void
    {
        $response = $this->getJson('/api/v1/time-entries');
        $response->assertOk();
    }

    public function test_time_entries_summary(): void
    {
        $response = $this->getJson('/api/v1/time-entries-summary');
        $response->assertOk();
    }

    public function test_start_time_entry(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson('/api/v1/time-entries/start', [
            'work_order_id' => $wo->id,
        ]);
        $response->assertCreated();
    }
}
