<?php

namespace App\Services;

use App\Services\Integration\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class ExternalApiService
{
    protected function fetch(string $url, string $cacheKey, int $cacheTtlSeconds): ?array
    {
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $domain = parse_url($url, PHP_URL_HOST) ?: 'unknown';

        return CircuitBreaker::for("external_api:{$domain}")
            ->withThreshold(5)
            ->withTimeout(120)
            ->executeOrFallback(function () use ($url, $cacheKey, $cacheTtlSeconds) {
                $response = Http::timeout(8)->retry(2, 300)->get($url);

                if ($response->failed()) {
                    Log::warning('External API request failed', [
                        'url' => $url,
                        'status' => $response->status(),
                    ]);

                    throw new \RuntimeException("External API returned HTTP {$response->status()}");
                }

                $data = $response->json();

                if ($data !== null) {
                    Cache::put($cacheKey, $data, $cacheTtlSeconds);
                }

                return $data;
            });
    }
}
