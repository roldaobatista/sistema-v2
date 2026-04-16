<?php

namespace App\Http\Controllers\Api\V1\Calibration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calibration\StoreLinearityTestsRequest;
use App\Http\Resources\LinearityTestResource;
use App\Models\EquipmentCalibration;
use App\Models\LinearityTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LinearityTestController extends Controller
{
    private function findCalibration(Request $request, int $calibrationId): EquipmentCalibration
    {
        $calibration = EquipmentCalibration::findOrFail($calibrationId);
        abort_unless($calibration->tenant_id === $request->user()->current_tenant_id, 404);

        return $calibration;
    }

    public function index(Request $request, int $calibration): JsonResponse
    {
        abort_unless($request->user()->can('calibration.reading.view'), 403);

        $cal = $this->findCalibration($request, $calibration);
        $tests = $cal->linearityTests()->orderBy('point_order')->paginate(15);

        return LinearityTestResource::collection($tests)->response();
    }

    public function store(StoreLinearityTestsRequest $request, int $calibration): JsonResponse
    {
        $cal = $this->findCalibration($request, $calibration);

        $precisionClass = $cal->precision_class;
        $eValue = (float) ($cal->verification_division_e ?? 0);
        $verificationType = $cal->verification_type ?? 'initial';

        $points = $request->validated()['points'];

        $tests = DB::transaction(function () use ($cal, $points, $precisionClass, $eValue, $verificationType, $request) {
            $cal->linearityTests()->delete();

            $created = [];
            foreach ($points as $index => $point) {
                $test = new LinearityTest([
                    'tenant_id' => $request->user()->current_tenant_id,
                    'equipment_calibration_id' => $cal->id,
                    'point_order' => $index + 1,
                    'reference_value' => $point['reference_value'],
                    'unit' => $point['unit'] ?? 'g',
                    'indication_increasing' => $point['indication_increasing'] ?? null,
                    'indication_decreasing' => $point['indication_decreasing'] ?? null,
                ]);

                $test->calculateErrors($precisionClass, $eValue, $verificationType);
                $test->save();

                $created[] = $test;
            }

            return $created;
        });

        return LinearityTestResource::collection(collect($tests))
            ->response()
            ->setStatusCode(201);
    }

    public function destroyAll(Request $request, int $calibration): JsonResponse
    {
        abort_unless($request->user()->can('calibration.reading.create'), 403);

        $cal = $this->findCalibration($request, $calibration);
        $cal->linearityTests()->delete();

        return response()->json(['message' => 'Pontos de linearidade removidos.']);
    }
}
