<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OperationalSnapshot;
use App\Services\Observability\ObservabilityDashboardService;
use Illuminate\Console\Command;

class RecordObservabilitySnapshot extends Command
{
    protected $signature = 'observability:snapshot';

    protected $description = 'Captura um snapshot operacional de observabilidade.';

    public function handle(ObservabilityDashboardService $dashboardService): int
    {
        $payload = $dashboardService->build();

        OperationalSnapshot::query()->create([
            'status' => (string) data_get($payload, 'summary.status', data_get($payload, 'health.status', 'healthy')),
            'alerts_count' => (int) data_get($payload, 'summary.active_alerts', 0),
            'health_payload' => data_get($payload, 'health', []),
            'metrics_payload' => data_get($payload, 'metrics', []),
            'alerts_payload' => data_get($payload, 'alerts', []),
            'captured_at' => now(),
        ]);

        $this->info('Observability snapshot recorded.');

        return self::SUCCESS;
    }
}
