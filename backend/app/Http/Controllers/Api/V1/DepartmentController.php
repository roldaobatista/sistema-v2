<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreDepartmentRequest;
use App\Http\Requests\Organization\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Department::where('tenant_id', $request->user()->current_tenant_id)
            ->latest()
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($items, resourceClass: DepartmentResource::class);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->current_tenant_id;
        $item = Department::create($data);

        return ApiResponse::data(new DepartmentResource($item), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $item = Department::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        return ApiResponse::data(new DepartmentResource($item));
    }

    public function update(UpdateDepartmentRequest $request, int $id): JsonResponse
    {
        $item = Department::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        $item->update($request->validated());

        return ApiResponse::data(new DepartmentResource($item));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = Department::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        $item->delete();

        return ApiResponse::noContent();
    }
}
