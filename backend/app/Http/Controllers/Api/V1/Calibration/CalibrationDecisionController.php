<?php

namespace App\Http\Controllers\Api\V1\Calibration;

use App\Actions\Calibration\EvaluateCalibrationDecisionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calibration\EvaluateDecisionRequest;
use App\Http\Resources\EquipmentCalibrationResource;
use App\Models\EquipmentCalibration;

class CalibrationDecisionController extends Controller
{
    public function evaluate(
        EquipmentCalibration $calibration,
        EvaluateDecisionRequest $request,
        EvaluateCalibrationDecisionAction $action,
    ): EquipmentCalibrationResource {
        $updated = $action->execute(
            calibration: $calibration,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        return new EquipmentCalibrationResource($updated);
    }
}
