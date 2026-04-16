<?php

namespace App\Http\Controllers\Api\V1\Metrology;

use App\Http\Controllers\Controller;
use App\Models\StandardWeight;
use App\Services\Metrology\WeightWearPredictorService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\Request;

class StandardWeightWearController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(private WeightWearPredictorService $wearService) {}

    /**
     * Trigger wear prediction calculation and return the updated metrics.
     */
    public function predict(int $id, Request $request)
    {
        $tenantId = $this->tenantId();

        $weight = StandardWeight::where('tenant_id', $tenantId)
            ->findOrFail($id);

        // Trigger prediction (this updates the model)
        $this->wearService->updateWearPrediction($weight);

        // Refresh model from DB to get updated fields
        $weight->refresh();

        return ApiResponse::data([
            'weight_id' => $weight->id,
            'name' => $weight->name ?? 'Peso Padrão',
            'wear_rate_percentage' => $weight->wear_rate_percentage,
            'expected_failure_date' => $weight->expected_failure_date,
        ]);
    }
}
