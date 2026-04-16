<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Observability;

use App\Services\Observability\ObservabilityMetricsService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ObservabilityMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Route::middleware('api')->get('/api/test-observability-middleware', function () {
            return ApiResponse::data(['ok' => true]);
        });
    }

    public function test_generates_correlation_and_request_headers_when_missing(): void
    {
        $response = $this->getJson('/api/test-observability-middleware');

        $response->assertOk()
            ->assertHeader('X-Correlation-ID')
            ->assertHeader('X-Request-ID');

        $this->assertNotEmpty($response->headers->get('X-Correlation-ID'));
        $this->assertSame(
            $response->headers->get('X-Correlation-ID'),
            $response->headers->get('X-Request-ID')
        );
    }

    public function test_reuses_incoming_correlation_id(): void
    {
        $response = $this->withHeaders([
            'X-Correlation-ID' => 'kalibrium-test-correlation',
        ])->getJson('/api/test-observability-middleware');

        $response->assertOk()
            ->assertHeader('X-Correlation-ID', 'kalibrium-test-correlation')
            ->assertHeader('X-Request-ID', 'kalibrium-test-correlation');
    }

    public function test_records_metrics_for_api_endpoint(): void
    {
        $this->getJson('/api/test-observability-middleware')->assertOk();

        $metrics = app(ObservabilityMetricsService::class)->endpointMetrics();

        $this->assertNotEmpty($metrics);
        $this->assertSame('/api/test-observability-middleware', $metrics[0]['path']);
        $this->assertGreaterThanOrEqual(1, $metrics[0]['count']);
    }
}
