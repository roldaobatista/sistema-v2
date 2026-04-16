<?php

namespace Tests\Unit\Services\Calibration;

use App\Models\EquipmentCalibration;
use App\Services\CalibrationCertificateService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CalibrationCertificateServiceTest extends TestCase
{
    protected CalibrationCertificateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CalibrationCertificateService::class);
    }

    public function test_generate_and_store_returns_existing_path_when_certificate_already_exists(): void
    {
        $calibration = EquipmentCalibration::factory()->create([
            'certificate_pdf_path' => 'public/certificates/calibration_existing.pdf',
        ]);

        // Create the file on disk so the idempotency guard finds it
        $fullPath = storage_path('app/public/certificates/calibration_existing.pdf');
        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, '%PDF-fake-content');

        $result = $this->service->generateAndStore($calibration);

        $this->assertEquals('certificates/calibration_existing.pdf', $result);

        // Clean up
        File::delete($fullPath);
    }

    public function test_generate_and_store_regenerates_when_path_set_but_file_missing(): void
    {
        $calibration = EquipmentCalibration::factory()->create([
            'certificate_pdf_path' => 'public/certificates/calibration_missing.pdf',
            'certificate_number' => 'CERT-000001',
        ]);

        // Do NOT create the file — the guard should NOT trigger, so generate() will be called.
        // We mock generate() to avoid needing full PDF infrastructure.
        $mock = $this->partialMock(CalibrationCertificateService::class, function ($mock) {
            $fakePdf = \Mockery::mock(PDF::class);
            $fakePdf->shouldReceive('output')->andReturn('%PDF-fake');
            $mock->shouldReceive('generate')->once()->andReturn($fakePdf);
        });

        $result = $mock->generateAndStore($calibration);

        $this->assertNotNull($result);
        $this->assertStringContainsString('certificates/', $result);

        // Clean up generated file
        $fullPath = storage_path("app/public/{$result}");
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }

    public function test_generate_and_store_regenerates_when_no_path_set(): void
    {
        $calibration = EquipmentCalibration::factory()->create([
            'certificate_pdf_path' => null,
            'certificate_number' => 'CERT-000002',
        ]);

        $mock = $this->partialMock(CalibrationCertificateService::class, function ($mock) {
            $fakePdf = \Mockery::mock(PDF::class);
            $fakePdf->shouldReceive('output')->andReturn('%PDF-fake');
            $mock->shouldReceive('generate')->once()->andReturn($fakePdf);
        });

        $result = $mock->generateAndStore($calibration);

        $this->assertNotNull($result);
        $this->assertStringContainsString('certificates/', $result);

        // Clean up
        $fullPath = storage_path("app/public/{$result}");
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
}
