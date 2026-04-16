<?php

namespace App\Services\Metrology;

use App\Models\StandardWeight;
use Carbon\Carbon;

class WeightWearPredictorService
{
    /**
     * Calculates and updates the wear rate and expected failure date for a standard weight.
     * Reads real calibration history via the calibrations() relationship.
     */
    public function updateWearPrediction(StandardWeight $weight): void
    {
        $nominal = (float) $weight->nominal_value;
        $mpe = $this->getMaximumPermissibleError($weight);

        // Busca calibrações reais ordenadas por data, com leituras
        $calibrations = $weight->calibrations()
            ->with('readings')
            ->orderBy('calibration_date')
            ->get();

        // Monta histórico de verificações: (data, massa medida)
        $historicalVerifications = [];
        foreach ($calibrations as $cal) {
            if (! $cal->calibration_date) {
                continue;
            }

            // Média das indicações crescentes nas leituras da calibração
            $readings = $cal->readings->filter(fn ($r) => $r->indication_increasing !== null);
            if ($readings->isEmpty()) {
                continue;
            }

            $measuredMass = $readings->avg('indication_increasing');

            $historicalVerifications[] = [
                'date' => Carbon::parse($cal->calibration_date),
                'measured_mass' => (float) $measuredMass,
            ];
        }

        if (count($historicalVerifications) < 2) {
            // Dados insuficientes para predição
            return;
        }

        $first = $historicalVerifications[0];
        $latest = end($historicalVerifications);

        $daysPassed = $first['date']->diffInDays($latest['date']);
        if ($daysPassed <= 0) {
            return;
        }

        $massLoss = $first['measured_mass'] - $latest['measured_mass'];
        $dailyWearRate = $massLoss / $daysPassed;

        $currentError = $nominal - $latest['measured_mass'];
        $wearPercentage = $mpe > 0 ? ($currentError / $mpe) * 100 : 0;

        $expectedFailureDate = null;
        if ($dailyWearRate > 0) {
            $remainingTolerance = $mpe - $currentError;
            $daysToFailure = $remainingTolerance / $dailyWearRate;
            $expectedFailureDate = Carbon::now()->addDays((int) $daysToFailure)->toDateString();
        }

        $weight->update([
            'wear_rate_percentage' => round($wearPercentage, 2),
            'expected_failure_date' => $expectedFailureDate,
        ]);
    }

    /**
     * MPE baseado na classe OIML (R 111-1) e valor nominal.
     * Retorna tolerância em unidade da balança (kg, g ou mg).
     */
    private function getMaximumPermissibleError(StandardWeight $weight): float
    {
        $nominal = (float) $weight->nominal_value;
        $class = $weight->precision_class ?? 'M1';

        // Fatores de tolerância relativa por classe OIML (aproximados)
        $factors = [
            'E1' => 0.000001,
            'E2' => 0.000003,
            'F1' => 0.00001,
            'F2' => 0.00003,
            'M1' => 0.0001,
            'M2' => 0.0003,
            'M3' => 0.001,
        ];

        return $nominal * ($factors[$class] ?? 0.0001);
    }
}
