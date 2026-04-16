<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AlertConfiguration;
use App\Models\SystemAlert;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlertControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Tenant $otherTenant;

    private User $otherUser;

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

        $this->otherTenant = Tenant::factory()->create();
        $this->otherUser = User::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'current_tenant_id' => $this->otherTenant->id,
        ]);
    }

    // ─── 1. LIST ALERTS WITH PAGINATION ───────────────────

    public function test_can_list_alerts_with_pagination(): void
    {
        SystemAlert::factory()->count(30)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/alerts?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.total', 30);
    }

    // ─── 2. LIST ALERTS WITH STATUS FILTER ────────────────

    public function test_can_filter_alerts_by_status(): void
    {
        SystemAlert::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
        SystemAlert::factory()->count(2)->resolved()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/alerts?status=active');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 3);
    }

    // ─── 3. LIST ALERTS WITH TYPE FILTER ──────────────────

    public function test_can_filter_alerts_by_type(): void
    {
        SystemAlert::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'low_stock',
        ]);
        SystemAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'sla_breach',
        ]);

        $response = $this->getJson('/api/v1/alerts?type=low_stock');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    // ─── 4. LIST ALERTS WITH SEVERITY FILTER ──────────────

    public function test_can_filter_alerts_by_severity(): void
    {
        SystemAlert::factory()->count(2)->critical()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        SystemAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'severity' => 'low',
        ]);

        $response = $this->getJson('/api/v1/alerts?severity=critical');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 2);
    }

    // ─── 5. GROUP BY ALERT TYPE ───────────────────────────

    public function test_can_group_alerts_by_type(): void
    {
        SystemAlert::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'low_stock',
        ]);
        SystemAlert::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'sla_breach',
        ]);

        $response = $this->getJson('/api/v1/alerts?group_by=alert_type');

        $response->assertStatus(200);
        $response->assertJsonPath('data.grouped', true);
        $this->assertCount(2, $response->json('data.data'));
    }

    // ─── 6. ACKNOWLEDGE ALERT ─────────────────────────────

    public function test_can_acknowledge_alert(): void
    {
        $alert = SystemAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/alerts/{$alert->id}/acknowledge");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'acknowledged');
        $response->assertJsonPath('data.acknowledged_by', $this->user->id);

        $alert->refresh();
        $this->assertEquals('acknowledged', $alert->status);
        $this->assertNotNull($alert->acknowledged_at);
        $this->assertEquals($this->user->id, $alert->acknowledged_by);
    }

    // ─── 7. RESOLVE ALERT ─────────────────────────────────

    public function test_can_resolve_alert(): void
    {
        $alert = SystemAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/alerts/{$alert->id}/resolve");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'resolved');

        $alert->refresh();
        $this->assertEquals('resolved', $alert->status);
        $this->assertNotNull($alert->resolved_at);
    }

    // ─── 8. DISMISS ALERT ─────────────────────────────────

    public function test_can_dismiss_alert(): void
    {
        $alert = SystemAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/alerts/{$alert->id}/dismiss");

        $response->assertStatus(200);
        $response->assertJsonStructure(['message']);

        $alert->refresh();
        $this->assertEquals('dismissed', $alert->status);
    }

    // ─── 9. ALERT SUMMARY ─────────────────────────────────

    public function test_can_get_alert_summary(): void
    {
        SystemAlert::factory()->count(2)->critical()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
        SystemAlert::factory()->high()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
        SystemAlert::factory()->resolved()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/alerts/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['total_active', 'critical', 'high', 'by_type'],
        ]);
        $response->assertJsonPath('data.total_active', 3);
        $response->assertJsonPath('data.critical', 2);
        $response->assertJsonPath('data.high', 1);
    }

    // ─── 10. CROSS-TENANT ISOLATION ───────────────────────

    public function test_cross_tenant_isolation_on_alerts_list(): void
    {
        SystemAlert::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        SystemAlert::factory()->count(5)->create(['tenant_id' => $this->otherTenant->id]);

        $response = $this->getJson('/api/v1/alerts');

        $response->assertStatus(200);
        // Should only see own tenant's alerts
        $response->assertJsonPath('meta.total', 3);
    }

    // ─── 11. CROSS-TENANT ISOLATION ON SUMMARY ────────────

    public function test_cross_tenant_isolation_on_summary(): void
    {
        SystemAlert::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
        SystemAlert::factory()->count(10)->create([
            'tenant_id' => $this->otherTenant->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/alerts/summary');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_active', 2);
    }

    // ─── 12. ALERT CONFIGS ────────────────────────────────

    public function test_can_list_alert_configs(): void
    {
        AlertConfiguration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'low_stock',
        ]);
        AlertConfiguration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'sla_breach',
        ]);

        $response = $this->getJson('/api/v1/alerts/configs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $this->assertCount(2, $response->json('data'));
    }

    // ─── 13. UPDATE ALERT CONFIG ──────────────────────────

    public function test_can_update_alert_config(): void
    {
        $response = $this->putJson('/api/v1/alerts/configs/low_stock', [
            'is_enabled' => true,
            'channels' => ['email', 'database'],
            'days_before' => 7,
            'recipients' => [$this->user->id],
            'escalation_hours' => 24,
            'escalation_recipients' => [$this->user->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.alert_type', 'low_stock');
        $response->assertJsonPath('data.is_enabled', true);
        $response->assertJsonPath('data.days_before', 7);

        $this->assertDatabaseHas('alert_configurations', [
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'low_stock',
            'is_enabled' => true,
        ]);
    }

    // ─── 14. UPDATE ALERT CONFIG - UPSERT BEHAVIOR ───────

    public function test_update_alert_config_creates_if_not_exists(): void
    {
        $this->assertDatabaseMissing('alert_configurations', [
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'overdue_receivable',
        ]);

        $response = $this->putJson('/api/v1/alerts/configs/overdue_receivable', [
            'is_enabled' => false,
            'days_before' => 5,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('alert_configurations', [
            'tenant_id' => $this->tenant->id,
            'alert_type' => 'overdue_receivable',
            'is_enabled' => false,
        ]);
    }

    // ─── 15. UPDATE ALERT CONFIG VALIDATION ───────────────

    public function test_update_alert_config_validates_input(): void
    {
        $response = $this->putJson('/api/v1/alerts/configs/low_stock', [
            'is_enabled' => 'not_a_boolean',
            'escalation_hours' => -5,
            'threshold_amount' => -100,
        ]);

        $response->assertStatus(422);
    }

    // ─── 16. EXPORT ALERTS CSV ────────────────────────────

    public function test_can_export_alerts_csv(): void
    {
        SystemAlert::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->get('/api/v1/alerts/export');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContains('attachment', $response->headers->get('content-disposition'));
    }

    // ─── 17. PER_PAGE CAPPED AT 100 ──────────────────────

    public function test_per_page_is_capped_at_100(): void
    {
        SystemAlert::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/alerts?per_page=999');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 100);
    }

    // ─── 18. ACKNOWLEDGE NONEXISTENT ALERT → 404 ─────────

    public function test_acknowledge_nonexistent_alert_returns_404(): void
    {
        $response = $this->postJson('/api/v1/alerts/99999/acknowledge');

        $response->assertStatus(404);
    }

    // ─── 19. RESOLVE NONEXISTENT ALERT → 404 ─────────────

    public function test_resolve_nonexistent_alert_returns_404(): void
    {
        $response = $this->postJson('/api/v1/alerts/99999/resolve');

        $response->assertStatus(404);
    }

    // ─── 20. DISMISS NONEXISTENT ALERT → 404 ─────────────

    public function test_dismiss_nonexistent_alert_returns_404(): void
    {
        $response = $this->postJson('/api/v1/alerts/99999/dismiss');

        $response->assertStatus(404);
    }

    // ─── HELPER ───────────────────────────────────────────

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
