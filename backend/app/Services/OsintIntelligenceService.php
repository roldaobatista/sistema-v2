<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OsintIntelligenceService
{
    /**
     * Queries external OSINT APIs (e.g., Jusbrasil API, Procon registries, Dark Web Brokers)
     * to determine the threat level of a technical assistance CNPJ (e.g., history of frauds).
     */
    public function analyzeThreatLevel(string $document): array
    {
        $document = preg_replace('/\D/', '', $document);

        // Sanity check
        if (empty($document)) {
            return ['level' => 'unknown', 'details' => 'Sem documento'];
        }

        try {
            // Mock: Integration with OSINT data provider / Threat Intelligence
            Log::info("OSINT checking threat level for document: {$document}");

            // Example response parsing
            // $response = Http::get("https://osint.api.provider/v1/entities/{$document}/threat-intel");
            // if ($response->successful()) { ... }

            // Temporary mock logic based on document ending to simulate OSINT findings
            $lastDigit = substr($document, -1);

            if ($lastDigit === '9') {
                return [
                    'level' => 'high',
                    'details' => 'Encontradas menções no PROCON e Jusbrasil sobre lacres falsos.',
                ];
            }

            if ($lastDigit === '7') {
                return [
                    'level' => 'medium',
                    'details' => 'Histórico de reclamações em fóruns sobre manutenção mal feita.',
                ];
            }

            return [
                'level' => 'safe',
                'details' => 'Sem menções negativas em bases OSINT públicas.',
            ];

        } catch (\Exception $e) {
            Log::error('OSINT Intelligence Service failed: '.$e->getMessage());

            return ['level' => 'unknown', 'details' => 'Erro na busca OSINT'];
        }
    }
}
