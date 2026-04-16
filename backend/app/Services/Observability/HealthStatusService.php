<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class HealthStatusService
{
    /**
     * @return array{
     *   status: string,
     *   timestamp: string,
     *   checks: array<string, array<string, mixed>>
     * }
     */
    public function status(): array
    {
        /** @var array<string, array<string, mixed>> $checks */
        $checks = [
            'mysql' => $this->checkMysql(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'disk' => $this->checkDisk(),
            'reverb' => $this->checkSocketService(
                'reverb',
                (string) config('reverb.apps.apps.0.options.host', config('reverb.servers.reverb.hostname', '127.0.0.1')),
                (int) config('reverb.apps.apps.0.options.port', 8080)
            ),
        ];

        $otelEndpoint = (string) config('services.observability.otel_endpoint', '');
        if ($otelEndpoint !== '') {
            $checks['collector'] = $this->checkSocketService(
                'collector',
                (string) parse_url($otelEndpoint, PHP_URL_HOST),
                4318
            );
        }

        $allHealthy = collect($checks)->every(static fn (array $check): bool => (bool) ($check['ok'] ?? false));

        return [
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => (string) now()->toISOString(),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkMysql(): array
    {
        try {
            DB::connection()->getPdo();
            $version = DB::selectOne('SELECT VERSION() as version');

            return [
                'ok' => true,
                'version' => $version->version ?? null,
            ];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'error' => 'Unavailable'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkRedis(): array
    {
        try {
            $pong = Redis::ping();

            return ['ok' => $pong === true || $pong === 'PONG'];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'error' => 'Unavailable'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkQueue(): array
    {
        try {
            $pending = Queue::size('default');
            $failed = Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->count()
                : 0;

            return [
                'ok' => true,
                'pending_jobs' => $pending,
                'failed_jobs' => $failed,
            ];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'error' => 'Unavailable'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDisk(): array
    {
        try {
            $storagePath = storage_path();
            $free = disk_free_space($storagePath);
            $total = disk_total_space($storagePath);
            $usedPercent = $total > 0
                ? round((1 - ($free / $total)) * 100, 2)
                : 100.0;

            $file = 'health/'.uniqid('probe_', true).'.txt';
            Storage::put($file, 'ok');
            $contents = Storage::get($file);
            Storage::delete($file);

            return [
                'ok' => $contents === 'ok' && $usedPercent < 90,
                'used_percent' => $usedPercent,
                'free_gb' => round($free / 1073741824, 2),
            ];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'error' => 'Unavailable'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkSocketService(string $name, string $host, int $port): array
    {
        $host = $host !== '' && $host !== '0.0.0.0' ? $host : '127.0.0.1';

        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, 0.5);
            if (is_resource($socket)) {
                fclose($socket);

                return [
                    'ok' => true,
                    'host' => $host,
                    'port' => $port,
                ];
            }

            return [
                'ok' => false,
                'host' => $host,
                'port' => $port,
                'error' => $errstr !== '' ? $errstr : 'Unavailable',
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'host' => $host,
                'port' => $port,
                'error' => 'Unavailable',
            ];
        }
    }
}
