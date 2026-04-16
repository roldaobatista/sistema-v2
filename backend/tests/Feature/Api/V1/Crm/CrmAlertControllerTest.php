<?php

namespace Tests\Feature\Api\V1\Crm;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmSmartAlert;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmAlertControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createAlert(?int $tenantId = null, string $priority = 'high', string $status = 'pending', string $title = 'Deal parado'): CrmSmartAlert
    {
        return CrmSmartAlert::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'type' => 'deal_stalled',
            'priority' => $priority,
            'title' => $title,
            'description' => 'Descrição do alerta',
            'status' => $status,
        ]);
    }

    public function test_smart_alerts_returns_only_current_tenant_sorted_by_priority(): void
    {
        $this->createAlert(null, 'low', 'pending', 'Alerta low');
        $this->createAlert(null, 'critical', 'pending', 'Alerta crítico');

        $otherTenant = Tenant::factory()->create();
        $this->createAlert($otherTenant->id, 'critical', 'pending', 'LEAK alerta');

        $response = $this->getJson('/api/v1/crm-features/alerts');

        $response->assertOk()->assertJsonStructure(['data']);
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK alerta', $json);

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertSame('Alerta crítico', $titles[0] ?? null, 'Critical deve aparecer antes de low');
    }

    public function test_smart_alerts_filters_by_status(): void
    {
        $this->createAlert(null, 'high', 'pending', 'Pendente');
        $this->createAlert(null, 'high', 'resolved', 'Resolvido');

        $response = $this->getJson('/api/v1/crm-features/alerts?status=resolved');

        $response->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertSame('resolved', $row['status']);
        }
    }

    public function test_acknowledge_alert_updates_status_and_timestamp(): void
    {
        $alert = $this->createAlert();

        $response = $this->putJson("/api/v1/crm-features/alerts/{$alert->id}/acknowledge");

        $response->assertOk();
        $this->assertDatabaseHas('crm_smart_alerts', [
            'id' => $alert->id,
            'status' => 'acknowledged',
        ]);
        $this->assertNotNull($alert->fresh()->acknowledged_at);
    }

    public function test_resolve_alert_updates_status(): void
    {
        $alert = $this->createAlert();

        $response = $this->putJson("/api/v1/crm-features/alerts/{$alert->id}/resolve");

        $response->assertOk();
        $this->assertDatabaseHas('crm_smart_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
        ]);
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_dismiss_alert_updates_status(): void
    {
        $alert = $this->createAlert();

        $response = $this->putJson("/api/v1/crm-features/alerts/{$alert->id}/dismiss");

        $response->assertOk();
        $this->assertDatabaseHas('crm_smart_alerts', [
            'id' => $alert->id,
            'status' => 'dismissed',
        ]);
    }
}
