<?php

namespace App\Http\Controllers\Api\V1\Lgpd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lgpd\StoreLgpdDataTreatmentRequest;
use App\Http\Resources\LgpdDataTreatmentResource;
use App\Models\LgpdDataTreatment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LgpdDataTreatmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $treatments = LgpdDataTreatment::query()
            ->when($request->input('legal_basis'), fn ($q, $v) => $q->where('legal_basis', $v))
            ->when($request->input('search'), fn ($q, $v) => $q->where('data_category', 'like', "%{$v}%"))
            ->orderBy('data_category')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($treatments, resourceClass: LgpdDataTreatmentResource::class);
    }

    public function store(StoreLgpdDataTreatmentRequest $request): JsonResponse
    {
        $treatment = LgpdDataTreatment::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->current_tenant_id,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::data(new LgpdDataTreatmentResource($treatment), 201);
    }

    public function show(int $id): JsonResponse
    {
        $treatment = LgpdDataTreatment::findOrFail($id);

        return ApiResponse::data(new LgpdDataTreatmentResource($treatment));
    }

    public function update(StoreLgpdDataTreatmentRequest $request, int $id): JsonResponse
    {
        $treatment = LgpdDataTreatment::findOrFail($id);
        $treatment->update($request->validated());

        return ApiResponse::data(new LgpdDataTreatmentResource($treatment));
    }

    public function destroy(int $id): JsonResponse
    {
        $treatment = LgpdDataTreatment::findOrFail($id);
        $treatment->delete();

        return ApiResponse::noContent();
    }
}
