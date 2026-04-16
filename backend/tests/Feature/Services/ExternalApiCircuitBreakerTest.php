<?php

namespace Tests\Feature\Services;

use App\Services\ExternalApiService;
use App\Services\Integration\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalApiCircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_external_api_returns_data_on_success(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response(['cnpj' => '12345678000100', 'razao_social' => 'Test'], 200),
        ]);

        $service = new class extends ExternalApiService
        {
            public function testFetch(string $url): ?array
            {
                return $this->fetch($url, 'test_cb_success', 60);
            }
        };

        $result = $service->testFetch('https://brasilapi.com.br/api/cnpj/v1/12345678000100');

        $this->assertNotNull($result);
        $this->assertEquals('12345678000100', $result['cnpj']);
    }

    public function test_external_api_returns_null_on_failure(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response(null, 500),
        ]);

        $service = new class extends ExternalApiService
        {
            public function testFetch(string $url): ?array
            {
                return $this->fetch($url, 'test_cb_fail_'.uniqid(), 60);
            }
        };

        $result = $service->testFetch('https://brasilapi.com.br/api/cnpj/v1/12345678000100');

        $this->assertNull($result);
    }

    public function test_external_api_circuit_opens_after_repeated_failures(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response(null, 500),
        ]);

        $service = new class extends ExternalApiService
        {
            public function testFetch(string $url, string $uniqueKey): ?array
            {
                return $this->fetch($url, $uniqueKey, 60);
            }
        };

        // 5 failures should trip the circuit (threshold = 5)
        for ($i = 0; $i < 5; $i++) {
            $service->testFetch(
                'https://brasilapi.com.br/api/cnpj/v1/12345678000100',
                'test_cb_trip_'.$i
            );
        }

        // Circuit should be open now
        $cb = CircuitBreaker::for('external_api:brasilapi.com.br');
        $this->assertTrue($cb->isOpen());

        // Next call should return null immediately (fallback) without hitting HTTP
        Http::fake([
            'brasilapi.com.br/*' => Http::response(['cnpj' => 'should_not_reach'], 200),
        ]);

        $result = $service->testFetch(
            'https://brasilapi.com.br/api/cnpj/v1/12345678000100',
            'test_cb_open_call'
        );

        $this->assertNull($result);
    }

    public function test_cached_response_skips_circuit_breaker(): void
    {
        $cacheKey = 'test_cached_skip_cb';

        Cache::put($cacheKey, ['cached' => true], 60);

        // Even if HTTP would fail, cache returns data
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $service = new class extends ExternalApiService
        {
            public function test_fetch(string $url, string $key): ?array
            {
                return $this->fetch($url, $key, 60);
            }
        };

        $result = $service->test_fetch('https://example.com/api/test', $cacheKey);

        $this->assertNotNull($result);
        $this->assertTrue($result['cached']);
    }
}
