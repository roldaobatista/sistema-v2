<?php

namespace Tests\Feature;

use App\Enums\ServiceCallStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckReportExportPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AlertConfiguration;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\SystemAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AlertEngineService;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CentralAlertsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware([
            CheckPermission::class,
            CheckReportExportPermission::class,
        ]);
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function grant(string ...$permissions): void
    {
        setPermissionsTeamId($this->tenant->id);
        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_alerts_index_requires_platform_dashboard_view(): void
    {
        $this->getJson('/api/v1/alerts')
            ->assertForbidden();

        $this->grant('platform.dashboard.view');

        $this->getJson('/api/v1/alerts', ['status' => 'active'])
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_alerts_summary_returns_counts(): void
    {
        $this->grant('platform.dashboard.view');

        SystemAlert::create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'unbilled_wo',
            'severity' => 'critical',
            'title' => 'Test',
            'message' => 'Msg',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/alerts/summary');
        $response->assertOk()
            ->assertJsonStructure(['total_active', 'critical', 'high', 'by_type']);
        $this->assertGreaterThanOrEqual(1, $response->json('data.total_active'));
    }

    public function test_alerts_index_with_group_by_alert_type(): void
    {
        $this->grant('platform.dashboard.view');

        SystemAlert::create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'low_stock',
            'severity' => 'high',
            'title' => 'Stock',
            'message' => 'Low',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/alerts?status=active&group_by=alert_type');
        $response->assertOk()
            ->assertJsonPath('data.grouped', true)
            ->assertJsonStructure(['data']);
        $this->assertIsArray($response->json('data'));
    }

    public function test_alerts_export_returns_csv_stream(): void
    {
        $this->grant('platform.dashboard.view');

        $response = $this->get('/api/v1/alerts/export?status=active');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_acknowledge_resolve_dismiss_alert(): void
    {
        $this->grant('platform.dashboard.view');

        $alert = SystemAlert::create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'quote_expiring',
            'severity' => 'medium',
            'title' => 'Quote',
            'message' => 'Expiring',
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/alerts/{$alert->id}/acknowledge")
            ->assertOk();
        $alert->refresh();
        $this->assertSame('acknowledged', $alert->status);
        $this->assertNotNull($alert->acknowledged_at);

        $alert->update(['status' => 'active']);
        $this->postJson("/api/v1/alerts/{$alert->id}/resolve")
            ->assertOk();
        $alert->refresh();
        $this->assertSame('resolved', $alert->status);

        $alert->update(['status' => 'active']);
        $this->postJson("/api/v1/alerts/{$alert->id}/dismiss")
            ->assertOk();
        $alert->refresh();
        $this->assertSame('dismissed', $alert->status);
    }

    public function test_run_engine_requires_admin_settings_manage(): void
    {
        $this->grant('platform.dashboard.view');
        $this->postJson('/api/v1/alerts/run-engine')
            ->assertForbidden();

        $this->grant('platform.dashboard.view', 'admin.settings.manage');
        $response = $this->postJson('/api/v1/alerts/run-engine');
        $response->assertOk()
            ->assertJsonStructure(['message', 'results']);
        $this->assertIsArray($response->json('data.results'));
    }

    public function test_alert_configs_index_and_update(): void
    {
        $this->grant('admin.settings.manage');

        $this->getJson('/api/v1/alerts/configs')
            ->assertOk();

        $response = $this->putJson('/api/v1/alerts/configs/expiring_calibration', [
            'is_enabled' => true,
            'days_before' => 30,
            'channels' => ['system'],
            'escalation_hours' => 4,
            'blackout_start' => '22:00',
            'blackout_end' => '06:00',
        ]);
        $response->assertOk()
            ->assertJsonPath('data.alert_type', 'expiring_calibration')
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.days_before', 30)
            ->assertJsonPath('data.escalation_hours', 4)
            ->assertJsonPath('data.blackout_start', '22:00')
            ->assertJsonPath('data.blackout_end', '06:00');

        $this->assertDatabaseHas('alert_configurations', [
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'expiring_calibration',
            'escalation_hours' => 4,
        ]);
    }

    public function test_alert_engine_run_all_checks_returns_array(): void
    {
        $engine = app(AlertEngineService::class);
        $results = $engine->runAllChecks($this->tenant->id);

        $this->assertIsArray($results);
        $expectedKeys = [
            'unbilled_wo', 'expiring_contract', 'expiring_calibration', 'calibration_overdue',
            'weight_cert_expiring', 'quote_expiring', 'quote_expired', 'overdue_receivable',
            'tool_cal_expiring', 'tool_cal_overdue', 'expense_pending', 'low_stock',
            'overdue_payable', 'expiring_payable', 'expiring_fleet_insurance', 'expiring_supplier_contract',
            'commitment_overdue', 'important_date_upcoming', 'customer_no_contact', 'overdue_follow_up',
            'unattended_service_call', 'renegotiation_pending', 'receivables_concentration', 'scheduled_wo_not_started',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $results, "Missing key: {$key}");
            $this->assertIsInt($results[$key]);
        }
    }

    public function test_quote_alert_checks_use_current_quote_statuses(): void
    {
        AlertConfiguration::create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'quote_expiring',
            'is_enabled' => true,
            'days_before' => 5,
            'channels' => ['system'],
        ]);

        AlertConfiguration::create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'quote_expired',
            'is_enabled' => true,
            'days_before' => 5,
            'channels' => ['system'],
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Quote Alert',
        ]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $this->user->id,
            'quote_number' => 'ORC-ALERT-001',
            'status' => 'internally_approved',
            'valid_until' => now()->addDays(2),
            'total' => 1500,
        ]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $this->user->id,
            'quote_number' => 'ORC-ALERT-002',
            'status' => 'pending_internal_approval',
            'valid_until' => now()->subDay(),
            'total' => 900,
        ]);

        $engine = app(AlertEngineService::class);

        $expiringCount = $engine->checkExpiringQuotes($this->tenant->id);
        $expiredCount = $engine->checkQuoteExpired($this->tenant->id);

        $this->assertSame(1, $expiringCount);
        $this->assertSame(1, $expiredCount);
        $this->assertDatabaseHas('system_alerts', [
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'quote_expiring',
            'title' => 'Orçamento #ORC-ALERT-001 vence em 2 dias',
        ]);
        $this->assertDatabaseHas('system_alerts', [
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'quote_expired',
            'title' => 'Orçamento #ORC-ALERT-002 expirado',
        ]);
    }

    public function test_escalation_checks_run_without_error(): void
    {
        $engine = app(AlertEngineService::class);
        $count = $engine->runEscalationChecks($this->tenant->id);
        $this->assertIsInt($count);
    }

    public function test_unattended_service_call_alert_uses_current_statuses_only(): void
    {
        AlertConfiguration::create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'unattended_service_call',
            'is_enabled' => true,
            'days_before' => 0,
            'channels' => ['system'],
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Alerta',
        ]);

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
            'created_at' => now()->subMinutes(31),
            'updated_at' => now()->subMinutes(31),
        ]);

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => ServiceCallStatus::IN_PROGRESS->value,
            'created_at' => now()->subMinutes(90),
            'updated_at' => now()->subMinutes(90),
        ]);

        $engine = app(AlertEngineService::class);
        $count = $engine->checkUnattendedServiceCalls($this->tenant->id);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('system_alerts', [
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'unattended_service_call',
            'status' => 'active',
        ]);
        $this->assertSame(1, SystemAlert::where('tenant_id', $this->tenant->id)
            ->where('alert_type', 'unattended_service_call')
            ->count());
    }
}
