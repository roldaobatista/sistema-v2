<?php

use App\Jobs\Inmetro\ScrapeInstrumentDetailsJob;
use App\Models\InmetroCompetitor;
use App\Models\InmetroInstrument;
use App\Models\InmetroLocation;
use App\Models\InmetroOwner;
use App\Services\InmetroCompetitorTrackingService;
use App\Services\InmetroPsieScraperService;
use App\Services\OsintIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('executes a deep scrape job and discovers competitor automatically', function () {
    // 1. Arrange
    $owner = InmetroOwner::factory()->create(['tenant_id' => 1]);
    $location = InmetroLocation::factory()->create(['owner_id' => $owner->id]);
    $instrument = InmetroInstrument::factory()->create([
        'location_id' => $location->id,
        'inmetro_number' => 'INM-TEST-123',
    ]);

    $scraperMock = Mockery::mock(InmetroPsieScraperService::class);
    $scraperMock->shouldReceive('getInstrumentDetails')
        ->with('INM-TEST-123')
        ->once()
        ->andReturn([
            'success' => true,
            'history' => [
                [
                    'date' => now()->format('Y-m-d'),
                    'result_status' => 'reparado',
                    'executor_name' => 'NEW BALANCES LTDA',
                    'executor_document' => '12345678000199',
                ],
            ],
        ]);

    $trackingMock = Mockery::mock(InmetroCompetitorTrackingService::class)->makePartial();
    // Use the real implementation to test the discovery actually creating the record
    // Or we just rely on standard app behavior without mocking this part to test the integration.

    $osintMock = Mockery::mock(OsintIntelligenceService::class);
    $osintMock->shouldReceive('analyzeThreatLevel')
        ->with('12345678000199')
        ->once()
        ->andReturn(['level' => 'high', 'details' => 'Procon alert']);

    // We will just resolve from container and replace
    app()->instance(InmetroPsieScraperService::class, $scraperMock);
    app()->instance(OsintIntelligenceService::class, $osintMock);

    // Act
    $job = new ScrapeInstrumentDetailsJob($instrument);

    // Instead of dispatching, we run handle synchronously with dependencies
    $job->handle(
        app(InmetroPsieScraperService::class),
        app(InmetroCompetitorTrackingService::class), // using real tracking service to trigger DB entry
        app(OsintIntelligenceService::class)
    );

    // Assert
    // 1. Check instrument was updated
    expect($instrument->refresh()->last_scrape_status)->toBe('success');

    // 2. Check competitor was created
    $this->assertDatabaseHas('inmetro_competitors', [
        'cnpj' => '12345678000199',
        'name' => 'NEW BALANCES LTDA',
        'tenant_id' => 1,
    ]);

    $competitor = InmetroCompetitor::where('cnpj', '12345678000199')->first();

    // 3. Check history event was created and linked to competitor
    $this->assertDatabaseHas('inmetro_history', [
        'instrument_id' => $instrument->id,
        'event_type' => 'repair',
        'executor_document' => '12345678000199',
        'osint_threat_level' => 'high',
        'competitor_id' => $competitor->id,
    ]);
});
