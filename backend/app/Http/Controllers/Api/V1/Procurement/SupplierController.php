<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreSupplierRequest;
use App\Http\Requests\Procurement\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->with(['accountsPayable', 'contracts'])
            ->orderByDesc('updated_at')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($suppliers);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->current_tenant_id;

        $supplier = Supplier::create($data);

        return ApiResponse::data($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load(['accountsPayable', 'contracts']);

        return ApiResponse::data($supplier);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return ApiResponse::data($supplier->fresh());
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return ApiResponse::noContent();
    }
}
