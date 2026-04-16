<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

interface IpemCrawlerInterface
{
    /**
     * @return array<string, mixed>
     */
    public function scrapeDetails(string $inmetroNumber): array;
}

class InmetroIpemCrawlerFactory
{
    /**
     * Instantiates the correct IPEM crawler based on the state (UF)
     */
    public function make(string $uf): IpemCrawlerInterface
    {
        $uf = strtoupper(trim($uf));

        switch ($uf) {
            case 'SP':
                return new class implements IpemCrawlerInterface
                {
                    /**
                     * @return array<string, mixed>
                     */
                    public function scrapeDetails(string $inmetroNumber): array
                    {
                        Log::info("Scraping IPEM-SP details for $inmetroNumber");

                        // Mock implementation for IPEM-SP SGI integration
                        return [
                            'success' => true,
                            'history' => [],
                        ];
                    }
                };
            case 'MG':
                return new class implements IpemCrawlerInterface
                {
                    /**
                     * @return array<string, mixed>
                     */
                    public function scrapeDetails(string $inmetroNumber): array
                    {
                        Log::info("Scraping IPEM-MG details for $inmetroNumber");

                        // Mock implementation for IPEM-MG integration
                        return [
                            'success' => true,
                            'history' => [],
                        ];
                    }
                };
            default:
                // Fallback to default PSIE or generalized IPEM portal
                return new class implements IpemCrawlerInterface
                {
                    /**
                     * @return array<string, mixed>
                     */
                    public function scrapeDetails(string $inmetroNumber): array
                    {
                        Log::info("Scraping generic IPEM/PSIE details for $inmetroNumber");

                        return [
                            'success' => true,
                            'history' => [],
                        ];
                    }
                };
        }
    }
}
