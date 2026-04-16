<?php

namespace Tests\Unit\Services;

use App\Services\LocationValidationService;
use Tests\TestCase;

class LocationValidationServiceTest extends TestCase
{
    private LocationValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LocationValidationService;
    }

    public function test_valid_location_returns_not_spoofed(): void
    {
        $result = $this->service->validate([
            'accuracy' => 10.0,
            'speed' => 5.0,
        ]);

        $this->assertFalse($result->isSpoofed);
        $this->assertEmpty($result->reason);
    }

    public function test_high_accuracy_detects_spoofing(): void
    {
        $result = $this->service->validate([
            'accuracy' => 200.0,
            'speed' => 0.0,
        ]);

        $this->assertTrue($result->isSpoofed);
        $this->assertStringContainsString('GPS', $result->reason);
    }

    public function test_accuracy_at_boundary_150_is_not_spoofed(): void
    {
        $result = $this->service->validate([
            'accuracy' => 150.0,
            'speed' => 0.0,
        ]);

        $this->assertFalse($result->isSpoofed);
    }

    public function test_accuracy_above_150_is_spoofed(): void
    {
        $result = $this->service->validate([
            'accuracy' => 150.1,
            'speed' => 0.0,
        ]);

        $this->assertTrue($result->isSpoofed);
    }

    public function test_high_speed_detects_spoofing(): void
    {
        $result = $this->service->validate([
            'accuracy' => 10.0,
            'speed' => 60.0,
        ]);

        $this->assertTrue($result->isSpoofed);
        $this->assertStringContainsString('Velocidade', $result->reason);
    }

    public function test_speed_at_boundary_55_is_not_spoofed(): void
    {
        $result = $this->service->validate([
            'accuracy' => 10.0,
            'speed' => 55.0,
        ]);

        $this->assertFalse($result->isSpoofed);
    }

    public function test_speed_above_55_is_spoofed(): void
    {
        $result = $this->service->validate([
            'accuracy' => 10.0,
            'speed' => 55.1,
        ]);

        $this->assertTrue($result->isSpoofed);
    }

    public function test_null_accuracy_is_not_spoofed(): void
    {
        $result = $this->service->validate([
            'speed' => 5.0,
        ]);

        $this->assertFalse($result->isSpoofed);
    }

    public function test_null_speed_is_not_spoofed(): void
    {
        $result = $this->service->validate([
            'accuracy' => 10.0,
        ]);

        $this->assertFalse($result->isSpoofed);
    }

    public function test_empty_location_data_is_not_spoofed(): void
    {
        $result = $this->service->validate([]);

        $this->assertFalse($result->isSpoofed);
    }
}
