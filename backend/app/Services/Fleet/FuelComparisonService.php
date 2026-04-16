<?php

namespace App\Services\Fleet;

class FuelComparisonService
{
    /**
     * Dados padrão de rendimento para comparação de combustíveis.
     * - Etanol: ~70% do rendimento da gasolina
     * - Diesel: ~30% mais eficiente que gasolina em longa distância
     */
    public function compare(float $gasolinePrice, float $ethanolPrice, ?float $dieselPrice = null): array
    {
        $gasStr = (string) $gasolinePrice;
        $ethStr = (string) $ethanolPrice;

        $ratio = $gasolinePrice > 0 ? bcdiv($ethStr, $gasStr, 4) : '1';

        $recommendation = bccomp($ratio, '0.7', 4) < 0 ? 'ethanol' : 'gasoline';
        $savingsPercent = bcmul((string) abs((float) bcsub('1', $ratio, 4)), '100', 1);

        $result = [
            'gasoline_price' => bcadd($gasStr, '0', 3),
            'ethanol_price' => bcadd($ethStr, '0', 3),
            'ratio' => bcadd($ratio, '0', 3),
            'recommendation' => $recommendation,
            'recommendation_label' => $recommendation === 'ethanol' ? 'Abasteça com Etanol' : 'Abasteça com Gasolina',
            'savings_percent' => (float) $savingsPercent,
            'threshold' => 0.7,
        ];

        if ($dieselPrice !== null) {
            $dslStr = (string) $dieselPrice;
            $result['diesel_price'] = bcadd($dslStr, '0', 3);
            $result['diesel_cost_per_km'] = $dieselPrice > 0 ? bcdiv($dslStr, '8', 4) : '0'; // ~8 km/L média diesel
            $result['gasoline_cost_per_km'] = $gasolinePrice > 0 ? bcdiv($gasStr, '10', 4) : '0'; // ~10 km/L
            $result['ethanol_cost_per_km'] = $ethanolPrice > 0 ? bcdiv($ethStr, '7', 4) : '0'; // ~7 km/L
        }

        return $result;
    }

    /**
     * Simulação de custo por viagem.
     */
    public function simulateTrip(float $distanceKm, float $avgConsumption, float $fuelPrice): array
    {
        $distStr = (string) $distanceKm;
        $consStr = (string) $avgConsumption;
        $priceStr = (string) $fuelPrice;

        $litersNeeded = $avgConsumption > 0 ? bcdiv($distStr, $consStr, 4) : '0';
        $totalCost = bcmul($litersNeeded, $priceStr, 4);

        return [
            'distance_km' => $distanceKm,
            'avg_consumption_km_l' => $avgConsumption,
            'fuel_price' => $fuelPrice,
            'liters_needed' => bcadd($litersNeeded, '0', 2),
            'total_cost' => bcadd($totalCost, '0', 2),
            'cost_per_km' => $distanceKm > 0 ? bcdiv($totalCost, $distStr, 4) : '0',
        ];
    }
}
