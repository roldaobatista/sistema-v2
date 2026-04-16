<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReverseGeocodingService
{
    /**
     * Usa Nominatim (OpenStreetMap) para converter GPS em endereço.
     */
    public function resolve(float $lat, float $lng): ?string
    {
        $cacheKey = "geocode_{$lat}_{$lng}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($lat, $lng) {
            try {
                // Rate limit: 1 request per second for Nominatim as per policy.
                sleep(1);

                $response = Http::withHeaders([
                    'User-Agent' => 'Kalibrium-ERP/1.0',
                    'Accept-Language' => 'pt-BR,pt;q=0.9',
                ])->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 18,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    return $data['display_name'] ?? "Lat: {$lat}, Lng: {$lng}";
                }

                Log::warning("Reverse geocoding failed for {$lat},{$lng}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            } catch (\Exception $e) {
                Log::error('Reverse geocoding exception: '.$e->getMessage());
            }

            return "Lat: {$lat}, Lng: {$lng}"; // Fallback
        });
    }
}
