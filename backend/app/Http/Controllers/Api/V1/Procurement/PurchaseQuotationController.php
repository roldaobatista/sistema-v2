<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePurchaseQuotationRequest;
use App\Http\Requests\Procurement\UpdatePurchaseQuotationRequest;
use App\Models\PurchaseQuotation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseQuotationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $quotations = PurchaseQuotation::query()
            ->with(['supplier', 'items'])
            ->orderByDesc('updated_at')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($quotations);
    }

    public function store(StorePurchaseQuotationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->current_tenant_id;
        $data['created_by'] = $request->user()->id;

        $quotation = PurchaseQuotation::create($data);

        return ApiResponse::data($quotation->load(['supplier', 'items']), 201);
    }

    public function show(PurchaseQuotation $purchaseQuotation): JsonResponse
    {
        $purchaseQuotation->load(['supplier', 'items']);

        return ApiResponse::data($purchaseQuotation);
    }

    public function update(UpdatePurchaseQuotationRequest $request, PurchaseQuotation $purchaseQuotation): JsonResponse
    {
        $purchaseQuotation->update($request->validated());

        return ApiResponse::data($purchaseQuotation->fresh(['supplier', 'items']));
    }

    public function destroy(PurchaseQuotation $purchaseQuotation): JsonResponse
    {
        $purchaseQuotation->delete();

        return ApiResponse::noContent();
    }
}
