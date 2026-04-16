<?php

namespace App\Jobs\Inmetro;

use App\Models\InmetroInstrument;
use App\Services\InmetroCompetitorTrackingService;
use App\Services\InmetroPsieScraperService;
use App\Services\OsintIntelligenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeInstrumentDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 120;

    public function __construct(
        protected InmetroInstrument $instrument
    ) {}

    public function handle(
        InmetroPsieScraperService $scraper,
        InmetroCompetitorTrackingService $competitorTracking,
        OsintIntelligenceService $osint
    ): void {
        Log::info("Starting deep scrape for instrument: {$this->instrument->inmetro_number}");

        try {
            // 1. Scrape details from PSIE (or IPEM factory)
            $details = $scraper->getInstrumentDetails($this->instrument->inmetro_number);

            if (! $details['success']) {
                $this->instrument->update(['last_scrape_status' => 'failed', 'next_deep_scrape_at' => now()->addDays(1)]);
                Log::warning("Failed to scrape details for {$this->instrument->inmetro_number}", ['error' => $details['error']]);

                return;
            }

            // 2. Process History Events (Verifications, Reprovals, Repairs)
            foreach ($details['history'] as $event) {
                // Determine event type
                $eventType = match (strtolower($event['result_status'])) {
                    'aprovado' => 'verification',
                    'reprovado' => 'rejection',
                    'reparado' => 'repair',
                    default => 'unknown',
                };

                $history = $this->instrument->history()->firstOrCreate(
                    [
                        'event_date' => $event['date'],
                        'result' => $event['result_status'],
                    ],
                    [
                        'event_type' => $eventType,
                        'executor' => $event['executor_name'],
                        'executor_document' => $event['executor_document'] ?? null,
                        'source' => 'psie_deep_scrape',
                    ]
                );

                // 3. Auto-Discovery of Competitor (if Technical Assistance repaired it)
                if ($eventType === 'repair' && ! empty($event['executor_document'])) {
                    $competitor = $competitorTracking->discoverCompetitor($event['executor_document'], $event['executor_name'], $this->instrument->location->owner->tenant_id ?? null);

                    if ($competitor) {
                        $history->update(['competitor_id' => $competitor->id]);
                    }

                    // 4. OSINT / Dark Web Threat intel on the executor
                    $threatIntel = $osint->analyzeThreatLevel($event['executor_document']);
                    $history->update(['osint_threat_level' => $threatIntel['level'] ?? 'safe']);
                }
            }

            $this->instrument->update([
                'last_scrape_status' => 'success',
                'next_deep_scrape_at' => now()->addDays(30), // Schedule next scrape
            ]);

        } catch (\Exception $e) {
            $this->instrument->update(['last_scrape_status' => 'error', 'next_deep_scrape_at' => now()->addDays(1)]);
            Log::error('Error in ScrapeInstrumentDetailsJob: '.$e->getMessage());

            throw $e;
        }
    }
}
