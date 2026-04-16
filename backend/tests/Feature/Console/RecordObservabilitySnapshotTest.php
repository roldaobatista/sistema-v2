<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\OperationalSnapshot;
use App\Services\Observability\ObservabilityDashboardService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RecordObservabilitySnapshotTest extends TestCase
{
    public function test_command_persists_operational_snapshot(): void
    {
        $service = \Mockery::mock(ObservabilityDashboardService::class);
        $service->shouldReceive('build')->once()->andReturn([
            'summary' => ['status' => 'healthy', 'active_alerts' => 0],
            'health' => ['status' => 'healthy', 'checks' => ['mysql' => ['ok' => true]]],
            'metrics' => [['path' => '/api/health', 'count' => 1, 'p95_ms' => 10]],
            'alerts' => [],
            'history' => [],
            'links' => ['horizon' => '/horizon'],
        ]);
        $this->app->instance(ObservabilityDashboardService::class, $service);

        Artisan::call('observability:snapshot');

        $this->assertDatabaseHas('operational_snapshots', [
            'status' => 'healthy',
            'alerts_count' => 0,
        ]);
    }

    public function test_command_marks_snapshot_as_critical_when_alerts_exist(): void
    {
        $service = \Mockery::mock(ObservabilityDashboardService::class);
        $service->shouldReceive('build')->once()->andReturn([
            'summary' => ['status' => 'critical', 'active_alerts' => 2],
            'health' => ['status' => 'degraded', 'checks' => ['queue' => ['ok' => false]]],
            'metrics' => [['path' => '/api/v1/observability/dashboard', 'count' => 4, 'p95_ms' => 2500]],
            'alerts' => [
                ['level' => 'critical', 'message' => 'Queue threshold exceeded'],
                ['level' => 'critical', 'message' => 'Disk threshold exceeded'],
            ],
            'history' => [],
            'links' => ['horizon' => '/horizon'],
        ]);
        $this->app->instance(ObservabilityDashboardService::class, $service);

        Artisan::call('observability:snapshot');

        $snapshot = OperationalSnapshot::query()->latest('id')->first();

        $this->assertNotNull($snapshot);
        $this->assertSame('critical', $snapshot->status);
        $this->assertSame(2, $snapshot->alerts_count);
    }
}
