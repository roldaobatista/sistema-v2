<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $items = StockMovement::where('tenant_id', $tenantId)
            ->latest()
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($items, resourceClass: StockMovementResource::class);
    }

    public function store(StoreStockMovementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->current_tenant_id;
        $data['created_by'] = $request->user()->id;

        $item = StockMovement::create($data);

        return ApiResponse::data(new StockMovementResource($item), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $item = StockMovement::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        return ApiResponse::data(new StockMovementResource($item));
    }
}
