<?php

namespace Tests\Unit\Services\Integration;

use App\Services\Integration\CircuitBreaker;
use App\Services\Integration\IntegrationHealthService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IntegrationHealthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_health_status_returns_expected_structure(): void
    {
        $service = app(IntegrationHealthService::class);
        $status = $service->getHealthStatus();

        $this->assertArrayHasKey('integrations', $status);
        $this->assertArrayHasKey('summary', $status);
        $this->assertArrayHasKey('overall', $status);
        $this->assertArrayHasKey('checked_at', $status);

        $this->assertArrayHasKey('healthy', $status['summary']);
        $this->assertArrayHasKey('degraded', $status['summary']);
        $this->assertArrayHasKey('down', $status['summary']);
    }

    public function test_all_circuits_are_healthy_by_default(): void
    {
        $service = app(IntegrationHealthService::class);
        $status = $service->getHealthStatus();

        $this->assertEquals('healthy', $status['overall']);
        $this->assertEquals(0, $status['summary']['down']);
        $this->assertEquals(0, $status['summary']['degraded']);
    }

    public function test_detects_circuit_open_as_down(): void
    {
        // Trip the focusnfe circuit
        $cb = CircuitBreaker::for('focusnfe')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        $service = app(IntegrationHealthService::class);
        $status = $service->getHealthStatus();

        $this->assertNotEquals('healthy', $status['overall']);
        $this->assertGreaterThan(0, $status['summary']['down']);

        // Check degraded integrations includes focusnfe
        $degraded = $service->getDegradedIntegrations();
        $focusDown = array_filter($degraded, fn ($i) => $i['key'] === 'focusnfe');
        $this->assertNotEmpty($focusDown);
    }

    public function test_has_critical_failure_detects_focusnfe(): void
    {
        // FocusNFe is marked as critical
        $cb = CircuitBreaker::for('focusnfe')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        $service = app(IntegrationHealthService::class);
        $this->assertTrue($service->hasCriticalFailure());
    }

    public function test_non_critical_failure_does_not_trigger_critical(): void
    {
        // Auvo is NOT critical
        $cb = CircuitBreaker::for('auvo_api')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        $service = app(IntegrationHealthService::class);
        $this->assertFalse($service->hasCriticalFailure());
    }

    public function test_integration_has_correct_metadata(): void
    {
        $service = app(IntegrationHealthService::class);
        $status = $service->getHealthStatus();

        $focus = collect($status['integrations'])->firstWhere('key', 'focusnfe');
        $this->assertNotNull($focus);
        $this->assertEquals('FocusNFe (Notas Fiscais)', $focus['label']);
        $this->assertEquals('fiscal', $focus['category']);
        $this->assertTrue($focus['critical']);
    }
}
