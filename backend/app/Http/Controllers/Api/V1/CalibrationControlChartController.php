<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EquipmentCalibration;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\Request;

class CalibrationControlChartController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * Returns control chart data (Xbar-R) for an equipment's calibration history.
     * Used for SPC (Statistical Process Control) visualization per ISO 17025.
     */
    public function show(int $equipmentId, Request $request)
    {
        $tenantId = $this->tenantId();

        $calibrations = EquipmentCalibration::where('tenant_id', $tenantId)
            ->where('equipment_id', $equipmentId)
            ->orderBy('calibration_date', 'asc')
            ->limit(50)
            ->with(['readings' => fn ($query) => $query->orderBy('reading_order')])
            ->get(['id', 'calibration_date', 'result', 'performed_by']);

        $calibrations = $calibrations
            ->filter(fn (EquipmentCalibration $calibration) => $calibration->readings->isNotEmpty())
            ->values();

        if ($calibrations->isEmpty()) {
            return ApiResponse::data([
                'equipment_id' => $equipmentId,
                'chart_data' => [],
                'ucl' => null,
                'lcl' => null,
                'cl' => null,
            ]);
        }

        $chartData = [];
        $allErrors = [];

        foreach ($calibrations as $cal) {
            $errors = [];

            foreach ($cal->readings as $reading) {
                $nominal = (float) $reading->reference_value;
                $measured = $reading->indication_increasing !== null
                    ? (float) $reading->indication_increasing
                    : ($reading->indication_decreasing !== null ? (float) $reading->indication_decreasing : null);

                if ($nominal <= 0 || $measured === null) {
                    continue;
                }

                $error = (($measured - $nominal) / $nominal) * 100;
                $errors[] = round($error, 4);
            }

            if (! empty($errors)) {
                $avgError = array_sum($errors) / count($errors);
                $allErrors[] = $avgError;

                $chartData[] = [
                    'calibration_id' => $cal->id,
                    'date' => $cal->calibration_date,
                    'mean_error_pct' => round($avgError, 4),
                    'range' => round(max($errors) - min($errors), 4),
                    'result' => $cal->result,
                    'readings_count' => count($errors),
                ];
            }
        }

        // Calculate control limits (3-sigma)
        $cl = count($allErrors) > 0 ? array_sum($allErrors) / count($allErrors) : 0;
        $stdDev = $this->calculateStdDev($allErrors, $cl);
        $ucl = round($cl + (3 * $stdDev), 4);
        $lcl = round($cl - (3 * $stdDev), 4);

        return ApiResponse::data([
            'equipment_id' => $equipmentId,
            'chart_data' => $chartData,
            'ucl' => $ucl,
            'lcl' => $lcl,
            'cl' => round($cl, 4),
            'std_dev' => round($stdDev, 4),
            'total_calibrations' => count($chartData),
        ]);
    }

    private function calculateStdDev(array $values, float $mean): float
    {
        if (count($values) < 2) {
            return 0;
        }
        $variance = array_sum(array_map(fn ($v) => pow($v - $mean, 2), $values)) / (count($values) - 1);

        return sqrt($variance);
    }
}
