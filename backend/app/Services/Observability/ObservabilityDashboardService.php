<?php

declare(strict_types=1);

namespace App\Services\Observability;

use App\Models\OperationalSnapshot;

class ObservabilityDashboardService
{
    public function __construct(
        private readonly HealthStatusService $healthStatusService,
        private readonly ObservabilityMetricsService $metricsService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $health = $this->healthStatusService->status();
        $metrics = $this->metricsService->endpointMetrics();
        $alerts = $this->activeAlerts($health, $metrics);

        $history = OperationalSnapshot::query()
            ->latest('captured_at')
            ->limit(12)
            ->get(['id', 'status', 'alerts_count', 'captured_at'])
            ->map(static fn (OperationalSnapshot $snapshot): array => [
                'id' => $snapshot->id,
                'status' => $snapshot->status,
                'alerts_count' => $snapshot->alerts_count,
                'captured_at' => optional($snapshot->captured_at)->toISOString(),
            ])
            ->all();

        return [
            'summary' => [
                'status' => $alerts !== [] ? 'critical' : (string) $health['status'],
                'active_alerts' => count($alerts),
                'tracked_endpoints' => count($metrics),
            ],
            'health' => $health,
            'metrics' => $metrics,
            'alerts' => $alerts,
            'history' => $history,
            'links' => [
                'horizon' => '/horizon',
                'pulse' => '/pulse',
                'jaeger' => config('services.observability.jaeger_url', 'http://localhost:16686'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $health
     * @param  array<int, array<string, int|float|string>>  $metrics
     * @return array<int, array<string, mixed>>
     */
    private function activeAlerts(array $health, array $metrics): array
    {
        $alerts = [];

        $queuePending = (int) data_get($health, 'checks.queue.pending_jobs', 0);
        if ($queuePending > 1000) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'queue',
                'message' => 'Fila default acima do threshold de 1000 jobs.',
                'value' => $queuePending,
            ];
        }

        $diskUsed = (float) data_get($health, 'checks.disk.used_percent', 0);
        if ($diskUsed > 90) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'disk',
                'message' => 'Uso de disco acima de 90%.',
                'value' => $diskUsed,
            ];
        }

        foreach ($metrics as $metric) {
            if ((float) ($metric['p95_ms'] ?? 0) > 2000 || (float) ($metric['p99_ms'] ?? 0) > 2000) {
                $alerts[] = [
                    'level' => 'critical',
                    'type' => 'latency',
                    'message' => 'Latencia acima de 2000ms detectada.',
                    'path' => $metric['path'],
                    'value' => max((float) ($metric['p95_ms'] ?? 0), (float) ($metric['p99_ms'] ?? 0)),
                ];
            }
        }

        return $alerts;
    }
}
