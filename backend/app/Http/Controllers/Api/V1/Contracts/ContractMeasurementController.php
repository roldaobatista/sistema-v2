<?php

namespace App\Http\Controllers\Api\V1\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\StoreContractMeasurementRequest;
use App\Http\Requests\Contracts\UpdateContractMeasurementRequest;
use App\Models\ContractMeasurement;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ContractMeasurementController extends Controller
{
    public function index(Request $request)
    {
        $paginator = ContractMeasurement::with('contract')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($paginator);
    }

    public function store(StoreContractMeasurementRequest $request)
    {
        $data = $request->validated();
        $data['items'] = $data['items'] ?? [];
        $data['tenant_id'] = $request->user()->current_tenant_id;
        $data['created_by'] = $request->user()->id;

        $measurement = ContractMeasurement::create($data);

        return response()->json($measurement->load('contract'), 201);
    }

    public function show(ContractMeasurement $measurement)
    {
        return response()->json($measurement->load('contract'));
    }

    public function update(UpdateContractMeasurementRequest $request, ContractMeasurement $measurement)
    {
        $measurement->update($request->validated());

        return response()->json($measurement->load('contract'));
    }

    public function destroy(ContractMeasurement $measurement)
    {
        $measurement->delete();

        return response()->noContent();
    }
}
