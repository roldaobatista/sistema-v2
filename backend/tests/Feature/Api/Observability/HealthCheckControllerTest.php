<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Observability;

use App\Services\Observability\HealthStatusService;
use Tests\TestCase;

class HealthCheckControllerTest extends TestCase
{
    public function test_health_endpoint_returns_healthy_payload(): void
    {
        $service = \Mockery::mock(HealthStatusService::class);
        $service->shouldReceive('status')->once()->andReturn([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => [
                'mysql' => ['ok' => true],
                'redis' => ['ok' => true],
                'queue' => ['ok' => true],
                'disk' => ['ok' => true],
                'reverb' => ['ok' => true],
            ],
        ]);
        $this->app->instance(HealthStatusService::class, $service);

        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'timestamp',
                    'checks' => [
                        'mysql',
                        'redis',
                        'queue',
                        'disk',
                        'reverb',
                    ],
                ],
            ])
            ->assertJsonPath('data.status', 'healthy');
    }

    public function test_health_endpoint_returns_503_when_degraded(): void
    {
        $service = \Mockery::mock(HealthStatusService::class);
        $service->shouldReceive('status')->once()->andReturn([
            'status' => 'degraded',
            'timestamp' => now()->toISOString(),
            'checks' => [
                'mysql' => ['ok' => true],
                'redis' => ['ok' => false, 'error' => 'Unavailable'],
                'queue' => ['ok' => true],
                'disk' => ['ok' => true],
                'reverb' => ['ok' => false, 'error' => 'Unavailable'],
            ],
        ]);
        $this->app->instance(HealthStatusService::class, $service);

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJsonPath('data.status', 'degraded');
    }

    public function test_health_endpoint_is_public_and_returns_json_structure(): void
    {
        $service = \Mockery::mock(HealthStatusService::class);
        $service->shouldReceive('status')->once()->andReturn([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => [
                'mysql' => ['ok' => true],
                'redis' => ['ok' => true],
                'queue' => ['ok' => true],
                'disk' => ['ok' => true],
                'reverb' => ['ok' => true],
            ],
        ]);
        $this->app->instance(HealthStatusService::class, $service);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonStructure(['data' => ['status', 'timestamp', 'checks']]);
    }

    public function test_health_endpoint_does_not_require_authentication(): void
    {
        $service = \Mockery::mock(HealthStatusService::class);
        $service->shouldReceive('status')->once()->andReturn([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => [
                'mysql' => ['ok' => true],
                'redis' => ['ok' => true],
                'queue' => ['ok' => true, 'pending_jobs' => 0, 'failed_jobs' => 0],
                'disk' => ['ok' => true, 'used_percent' => 45.0, 'free_gb' => 50.0],
                'reverb' => ['ok' => true],
            ],
        ]);
        $this->app->instance(HealthStatusService::class, $service);

        // Explicitly no actingAs — unauthenticated request must NOT return 401/403
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_health_endpoint_includes_all_required_check_keys(): void
    {
        $service = \Mockery::mock(HealthStatusService::class);
        $service->shouldReceive('status')->once()->andReturn([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => [
                'mysql' => ['ok' => true, 'version' => '8.0.36'],
                'redis' => ['ok' => true],
                'queue' => ['ok' => true, 'pending_jobs' => 5, 'failed_jobs' => 0],
                'disk' => ['ok' => true, 'used_percent' => 45.0, 'free_gb' => 50.0],
                'reverb' => ['ok' => true],
            ],
        ]);
        $this->app->instance(HealthStatusService::class, $service);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'timestamp',
                    'checks' => [
                        'mysql' => ['ok'],
                        'redis' => ['ok'],
                        'queue' => ['ok', 'pending_jobs', 'failed_jobs'],
                        'disk' => ['ok', 'used_percent', 'free_gb'],
                    ],
                ],
            ]);
    }
}
