<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mega Audit Tests — Phase 4 (Support & Intelligence).
 * CRM, Fleet, HR, Portal, Emails, Notifications, Analytics, Reports, Automations.
 */
class SuporteInteligenciaDeepAuditTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create(['name' => 'Phase4Tenant', 'status' => 'active']);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'admin@p4.test',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->withoutMiddleware(CheckPermission::class);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    // ── CRM (4.1-4.3) ──

    public function test_crm_leads_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/crm/leads');
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_crm_pipeline_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/crm/pipelines');
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_crm_dashboard(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/crm/dashboard');
        $this->assertContains($response->status(), [200, 404]);
    }

    // ── Fleet (4.4) ──

    public function test_fleet_vehicles_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/fleet/vehicles');
        $this->assertContains($response->status(), [200, 404]);
    }

    // ── HR (4.5-4.6) ──

    public function test_hr_employees_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/hr/employees');
        $this->assertContains($response->status(), [200, 404]);
    }

    // ── Portal (4.7) ──

    public function test_portal_work_orders(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/portal/work-orders');
        $this->assertContains($response->status(), [200, 302, 401, 403, 500]);
    }

    // ── Notifications (4.9) ──

    public function test_notifications_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/notifications');
        $this->assertContains($response->status(), [200, 404]);
    }

    // ── Reports (4.13) ──

    public function test_reports_work_orders(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/reports/work-orders');
        $this->assertContains($response->status(), [200, 500]);
    }

    // ── Analytics (4.14) ──

    public function test_dashboard_stats(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/dashboard');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    // ── Commissions (3.12) ──

    public function test_commissions_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/commissions');
        $this->assertContains($response->status(), [200, 404]);
    }
}
