<?php

namespace App\Services;

use App\Models\TimeClockEntry;

class LocationValidationResult
{
    public bool $isSpoofed;

    public string $reason;

    public function __construct(bool $isSpoofed, string $reason = '')
    {
        $this->isSpoofed = $isSpoofed;
        $this->reason = $reason;
    }
}

class LocationValidationService
{
    /**
     * Detects spoofing: accuracy > 150m, speed > 55m/s (approx 200km/h)
     */
    public function validate(array $locationData): LocationValidationResult
    {
        $accuracy = isset($locationData['accuracy']) ? (float) $locationData['accuracy'] : null;
        $speed = isset($locationData['speed']) ? (float) $locationData['speed'] : null;

        if ($accuracy !== null && $accuracy > config('hr.portaria671.gps_max_accuracy_meters')) {
            return new LocationValidationResult(true, 'Precisão do GPS muito baixa (>'.config('hr.portaria671.gps_max_accuracy_meters').'m)');
        }

        if ($speed !== null && $speed > config('hr.portaria671.gps_max_speed_ms')) {
            return new LocationValidationResult(true, 'Velocidade incompatível detectada');
        }

        return new LocationValidationResult(false);
    }

    /**
     * Verifica consistência entre clock-in e clock-out (distância vs tempo)
     */
    public function validateConsistency(TimeClockEntry $entry): bool
    {
        if (! $entry->latitude_in || ! $entry->latitude_out) {
            return true;
        }

        $distance = $this->calculateDistance(
            (float) $entry->latitude_in, (float) $entry->longitude_in,
            (float) $entry->latitude_out, (float) $entry->longitude_out
        );

        $durationHours = $entry->duration_hours ?? 0;

        if ($durationHours <= 0) {
            return true;
        }

        $speedKmH = ($distance / 1000) / $durationHours;

        if ($speedKmH > config('hr.portaria671.max_consistency_speed_kmh')) {
            return false; // commercial jet speed
        }

        return true;
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
