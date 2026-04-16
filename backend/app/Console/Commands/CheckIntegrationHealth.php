<?php

namespace App\Console\Commands;

use App\Notifications\SystemAlertNotification;
use App\Services\Integration\IntegrationHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckIntegrationHealth extends Command
{
    protected $signature = 'integrations:health-check';

    protected $description = 'Verifica a saúde de todas as integrações externas e notifica se alguma estiver degradada';

    public function handle(IntegrationHealthService $health): int
    {
        $this->info('Verificando saúde das integrações...');

        /** @var array{
         *   integrations: array<int, array{label: string, status: string, state: string, failures: int}>,
         *   summary: array{healthy: int, degraded: int, down: int},
         *   overall: string
         * } $status
         */
        $status = $health->getHealthStatus();
        $degraded = $health->getDegradedIntegrations();

        // Report status
        $this->table(
            ['Integração', 'Status', 'Estado', 'Falhas'],
            array_map(fn (array $i) => [
                $i['label'],
                $i['status'],
                $i['state'],
                $i['failures'],
            ], $status['integrations'])
        );

        $this->info("Overall: {$status['overall']}");
        $this->info("Healthy: {$status['summary']['healthy']} | Degraded: {$status['summary']['degraded']} | Down: {$status['summary']['down']}");

        // Notify on critical failures
        if ($health->hasCriticalFailure()) {
            $criticalList = array_filter($degraded, fn (array $i) => $i['critical']);
            $names = implode(', ', array_column($criticalList, 'label'));

            $email = config('app.system_alert_email');
            if ($email) {
                Notification::route('mail', $email)
                    ->notify(new SystemAlertNotification(
                        'Integração Crítica Degradada',
                        "As seguintes integrações críticas estão com problemas: {$names}. Verificar imediatamente.",
                        'critical'
                    ));
            }

            Log::critical('Integration health: critical failure detected', [
                'degraded' => array_column($criticalList, 'key'),
            ]);

            $this->error("⚠️  INTEGRAÇÕES CRÍTICAS DEGRADADAS: {$names}");
        } elseif (count($degraded) > 0) {
            $names = implode(', ', array_column($degraded, 'label'));
            Log::warning('Integration health: non-critical degradation', [
                'degraded' => array_column($degraded, 'key'),
            ]);

            $this->warn("Integrações degradadas (não-críticas): {$names}");
        }

        return self::SUCCESS;
    }
}
