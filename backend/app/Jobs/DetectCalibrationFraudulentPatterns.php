<?php

namespace App\Jobs;

use App\Jobs\Middleware\SetTenantContext;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetectCalibrationFraudulentPatterns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(private ?int $tenantId = null)
    {
        $this->queue = 'quality';
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        if ($this->tenantId && $this->tenantId > 0) {
            return [new SetTenantContext($this->tenantId)];
        }

        return [];
    }

    public function handle(): void
    {
        // When running for all tenants (scheduler), iterate each one
        if (! $this->tenantId) {
            $tenantIds = Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id');
            foreach ($tenantIds as $tenantId) {
                app()->instance('current_tenant_id', $tenantId);
                $this->processCalibrations($tenantId);
            }

            return;
        }

        $this->processCalibrations($this->tenantId);
    }

    private function processCalibrations(int $tenantId): void
    {
        $calibrations = EquipmentCalibration::with('readings')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        // Group by technician to detect patterns
        $byTech = $calibrations->groupBy('performed_by');

        foreach ($byTech as $techId => $techCalibrations) {
            if ($techCalibrations->count() < 5) {
                continue;
            }

            // Pattern 1: Identical readings across multiple calibrations
            $this->detectIdenticalReadings($techCalibrations, $techId);

            // Pattern 2: Impossibly fast calibrations (< 5 min)
            $this->detectRapidCalibrations($techCalibrations, $techId);

            // Pattern 3: All results exactly at nominal (zero error)
            $this->detectPerfectResults($techCalibrations, $techId);
        }

        Log::info('DetectCalibrationFraudulentPatterns: analysis complete', [
            'calibrations_analyzed' => $calibrations->count(),
            'technicians_checked' => $byTech->count(),
        ]);
    }

    private function detectIdenticalReadings($calibrations, int $techId): void
    {
        // Compare JSON readings for suspicious patterns
        $readingHashes = [];
        foreach ($calibrations as $cal) {
            $readings = $cal->readings ?? [];
            if (empty($readings)) {
                continue;
            }
            $hash = md5(json_encode($readings));
            $readingHashes[$hash][] = $cal;
        }

        foreach ($readingHashes as $hash => $duplicates) {
            if (count($duplicates) >= 3) {
                $similarity = (count($duplicates) / $calibrations->count()) * 100;
                foreach ($duplicates as $cal) {
                    DB::table('qa_alerts')->updateOrInsert(
                        ['calibration_id' => $cal->id, 'reason' => 'identical_readings'],
                        [
                            'tenant_id' => $cal->tenant_id,
                            'similarity_score' => min($similarity, 100),
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    private function detectRapidCalibrations($calibrations, int $techId): void
    {
        foreach ($calibrations as $cal) {
            // started_at / completed_at columns are not present in the current schema.
            // Guard with Schema or attribute check to avoid MissingAttributeException.
            $startedAt = $cal->getAttributes()['started_at'] ?? null;
            $completedAt = $cal->getAttributes()['completed_at'] ?? null;

            if ($startedAt && $completedAt) {
                $minutes = Carbon::parse($startedAt)->diffInMinutes(Carbon::parse($completedAt));
                if ($minutes < 5 && $minutes >= 0) {
                    DB::table('qa_alerts')->updateOrInsert(
                        ['calibration_id' => $cal->id, 'reason' => 'rapid_completion'],
                        [
                            'tenant_id' => $cal->tenant_id,
                            'similarity_score' => max(90, 100 - ($minutes * 10)),
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    private function detectPerfectResults($calibrations, int $techId): void
    {
        $perfectCount = 0;
        foreach ($calibrations as $cal) {
            $readings = $cal->readings ?? [];
            if (empty($readings)) {
                continue;
            }

            $allPerfect = true;
            foreach ($readings as $reading) {
                $error = abs(($reading['measured'] ?? 0) - ($reading['nominal'] ?? 0));
                if ($error > 0.001) {
                    $allPerfect = false;
                    break;
                }
            }
            if ($allPerfect) {
                $perfectCount++;
            }
        }

        // If more than 80% of calibrations have perfect results, flag them
        if ($calibrations->count() > 0 && ($perfectCount / $calibrations->count()) > 0.8) {
            foreach ($calibrations as $cal) {
                DB::table('qa_alerts')->updateOrInsert(
                    ['calibration_id' => $cal->id, 'reason' => 'perfect_results_pattern'],
                    [
                        'tenant_id' => $cal->tenant_id,
                        'similarity_score' => round(($perfectCount / $calibrations->count()) * 100, 2),
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('DetectCalibrationFraudulentPatterns failed permanently', [
            'tenant_id' => $this->tenantId,
            'error' => $e->getMessage(),
        ]);
    }
}
