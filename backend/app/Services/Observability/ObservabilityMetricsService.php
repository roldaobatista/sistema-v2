<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ObservabilityMetricsService
{
    private const INDEX_KEY = 'observability:metrics:index';

    private const ENTRY_TTL_SECONDS = 3600;

    public function record(string $path, string $method, Response $response, float $durationMs): void
    {
        $path = '/'.ltrim($path, '/');
        $method = strtoupper($method);
        $signature = $method.' '.$path;
        $entryKey = $this->entryKey($signature);
        $now = (int) now()->timestamp;

        $samples = Cache::get($entryKey, []);
        if (! is_array($samples)) {
            $samples = [];
        }

        $samples[] = [
            'path' => $path,
            'method' => $method,
            'status' => $response->getStatusCode(),
            'duration_ms' => round($durationMs, 2),
            'recorded_at' => $now,
        ];

        $samples = array_values(array_filter(
            array_slice($samples, -200),
            static fn (array $sample): bool => (int) ($sample['recorded_at'] ?? 0) >= ($now - self::ENTRY_TTL_SECONDS)
        ));

        Cache::put($entryKey, $samples, self::ENTRY_TTL_SECONDS);

        $index = Cache::get(self::INDEX_KEY, []);
        if (! is_array($index)) {
            $index = [];
        }

        if (! in_array($signature, $index, true)) {
            $index[] = $signature;
            sort($index);
            Cache::put(self::INDEX_KEY, $index, self::ENTRY_TTL_SECONDS);
        }
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    public function endpointMetrics(): array
    {
        $index = Cache::get(self::INDEX_KEY, []);
        if (! is_array($index)) {
            return [];
        }

        $now = (int) now()->timestamp;
        $metrics = [];

        foreach ($index as $signature) {
            if (! is_string($signature)) {
                continue;
            }

            $samples = Cache::get($this->entryKey($signature), []);
            if (! is_array($samples) || $samples === []) {
                continue;
            }

            $samples = array_values(array_filter(
                $samples,
                static fn (array $sample): bool => (int) ($sample['recorded_at'] ?? 0) >= ($now - self::ENTRY_TTL_SECONDS)
            ));

            if ($samples === []) {
                continue;
            }

            $durations = array_map(
                static fn (array $sample): float => (float) ($sample['duration_ms'] ?? 0),
                $samples
            );

            sort($durations);

            $last = end($samples);
            $metrics[] = [
                'signature' => $signature,
                'path' => (string) ($last['path'] ?? ''),
                'method' => (string) ($last['method'] ?? 'GET'),
                'count' => count($samples),
                'last_status' => (int) ($last['status'] ?? 200),
                'p50_ms' => $this->percentile($durations, 50),
                'p95_ms' => $this->percentile($durations, 95),
                'p99_ms' => $this->percentile($durations, 99),
                'avg_ms' => round(array_sum($durations) / max(count($durations), 1), 2),
            ];
        }

        usort(
            $metrics,
            static fn (array $left, array $right): int => (int) ($right['p95_ms'] <=> $left['p95_ms'])
        );

        return $metrics;
    }

    private function entryKey(string $signature): string
    {
        return 'observability:metrics:'.sha1($signature);
    }

    /**
     * @param  array<int, float>  $values
     */
    private function percentile(array $values, int $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return round($values[$index], 2);
    }
}
