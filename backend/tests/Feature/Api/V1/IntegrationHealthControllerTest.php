<?php

namespace Tests\Feature\Api\V1;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Integration\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class IntegrationHealthControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        CircuitBreaker::clearRegistry();

        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        // Create permissions first
        foreach (['admin.settings.manage', 'integration.health.view', 'integration.health.reset'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->admin->givePermissionTo(['admin.settings.manage', 'integration.health.view', 'integration.health.reset']);
    }

    public function test_index_lists_all_circuit_breakers(): void
    {
        // Create some circuit breakers
        CircuitBreaker::for('esocial_api');
        CircuitBreaker::for('asaas_api');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/system/integrations/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'services',
                'total',
                'healthy',
                'degraded',
                'unhealthy',
            ],
        ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, $data['total']);
    }

    public function test_show_returns_specific_breaker_status(): void
    {

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/system/integrations/health/esocial_api');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['service', 'state', 'failure_count'],
        ]);
        $response->assertJsonPath('data.service', 'esocial_api');
        $response->assertJsonPath('data.state', 'closed');
    }

    public function test_reset_brings_circuit_to_closed(): void
    {

        // Trip a circuit
        $cb = CircuitBreaker::for('test_reset_svc')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }
        $this->assertTrue($cb->isOpen());

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/system/integrations/health/test_reset_svc/reset');

        $response->assertOk();
        $response->assertJsonPath('data.previous_state', 'open');
        $response->assertJsonPath('data.current_state', 'closed');

        // Verify it's actually closed
        $this->assertTrue(CircuitBreaker::for('test_reset_svc')->isClosed());
    }

    public function test_permission_denied_returns_403(): void
    {
        $regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($regularUser)
            ->getJson('/api/v1/system/integrations/health');

        $response->assertStatus(403);
    }

    public function test_index_shows_open_circuit_as_unhealthy(): void
    {

        // Trip a known service
        $cb = CircuitBreaker::for('asaas_api')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/system/integrations/health');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['unhealthy']);
    }

    public function test_show_after_recovery_shows_closed(): void
    {

        // Trip and reset
        $cb = CircuitBreaker::for('fiscal_nuvemfiscal')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }
        $cb->reset();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/system/integrations/health/fiscal_nuvemfiscal');

        $response->assertOk();
        $response->assertJsonPath('data.state', 'closed');
        $response->assertJsonPath('data.failure_count', 0);
    }

    public function test_known_services_always_included_in_index(): void
    {
        // Don't create any circuit breakers manually

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/system/integrations/health');

        $response->assertOk();
        $services = collect($response->json('data.services'))->pluck('service')->toArray();

        $this->assertContains('esocial_api', $services);
        $this->assertContains('asaas_api', $services);
        $this->assertContains('fiscal_nuvemfiscal', $services);
        $this->assertContains('fiscal_focusnfe', $services);
    }

    public function test_reset_permission_denied_returns_403(): void
    {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        // Give admin.settings.manage to pass route middleware, plus view only
        $viewer->givePermissionTo(['admin.settings.manage', 'integration.health.view']);

        $response = $this->actingAs($viewer)
            ->postJson('/api/v1/system/integrations/health/esocial_api/reset');

        $response->assertStatus(403);
    }
}
