<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardReportsDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->assignRole('admin');

    }

    // ── Dashboard ──

    public function test_dashboard_loads(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');
        $response->assertOk();
    }

    public function test_dashboard_returns_kpis(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');
        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_dashboard_work_orders_summary(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard/work-orders');
        $response->assertOk();
    }

    public function test_dashboard_financial_summary(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/reports/financial');
        $response->assertOk();
    }

    public function test_dashboard_recent_activities(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard/activities');
        $response->assertOk();
    }

    // ── Reports ──

    public function test_report_customers(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/reports/customers');
        $response->assertOk();
    }

    public function test_report_equipments(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/reports/equipments');
        $response->assertOk();
    }

    public function test_report_expenses(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/reports/expenses');
        $response->assertOk();
    }

    public function test_report_commissions(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/reports/commissions');
        $response->assertOk();
    }

    public function test_report_crm(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/reports/crm');
        $response->assertOk();
    }

    // ── Export ──

    public function test_export_customers_csv(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/exports/customers?format=csv');
        $this->assertTrue(in_array($response->status(), [200, 202]));
    }

    public function test_export_work_orders_csv(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/exports/work-orders?format=csv');
        $this->assertTrue(in_array($response->status(), [200, 202]));
    }

    public function test_export_financial_csv(): void
    {
        $response = $this->actingAs($this->user)->get('/api/v1/accounts-payable-export');
        $response->assertOk();
    }

    // ── Unauthenticated ──

    public function test_dashboard_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/dashboard');
        $response->assertUnauthorized();
    }

    public function test_reports_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/reports/work-orders');
        $response->assertUnauthorized();
    }

    // ── Filtered Reports ──

    public function test_report_work_orders_with_date_filter(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/api/v1/reports/work-orders?date_from=2026-01-01&date_to=2026-12-31'
        );
        $response->assertOk();
    }

    public function test_report_financial_with_month_filter(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/api/v1/reports/financial?month=2026-03'
        );
        $response->assertOk();
    }

    public function test_report_work_orders_with_status_filter(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/api/v1/reports/work-orders?status=completed'
        );
        $response->assertOk();
    }
}
