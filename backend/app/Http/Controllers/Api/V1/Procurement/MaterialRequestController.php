<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreMaterialRequestRequest;
use App\Http\Requests\Procurement\UpdateMaterialRequestRequest;
use App\Models\MaterialRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $materialRequests = MaterialRequest::query()
            ->with(['requester', 'workOrder', 'items'])
            ->orderByDesc('updated_at')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($materialRequests);
    }

    public function store(StoreMaterialRequestRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->current_tenant_id;
        $data['requester_id'] = $request->user()->id;

        $materialRequest = MaterialRequest::create($data);

        return ApiResponse::data($materialRequest->load(['requester', 'workOrder', 'items']), 201);
    }

    public function show(MaterialRequest $materialRequest): JsonResponse
    {
        $materialRequest->load(['requester', 'workOrder', 'items']);

        return ApiResponse::data($materialRequest);
    }

    public function update(UpdateMaterialRequestRequest $request, MaterialRequest $materialRequest): JsonResponse
    {
        $materialRequest->update($request->validated());

        return ApiResponse::data($materialRequest->fresh(['requester', 'workOrder', 'items']));
    }

    public function destroy(MaterialRequest $materialRequest): JsonResponse
    {
        $materialRequest->delete();

        return ApiResponse::noContent();
    }
}
